<?php

namespace App\Actions\Orders;

use App\Enums\RestaurantOrderStatus;
use App\Events\RestaurantOrderStatusChanged;
use App\Exceptions\InvalidRestaurantOrderTransitionException;
use App\Exceptions\PaymentNotCapturedException;
use App\Exceptions\PaymentRefundFailedException;
use App\Exceptions\RefundExceedsRemainingAmountException;
use App\Models\RestaurantOrder;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lunar\Base\PaymentTypeInterface;
use Lunar\Facades\Payments;
use Lunar\Models\Transaction;

class RefundRestaurantOrderAction
{
    /**
     * Refund a sub-order in full or in part through Lunar's payment
     * abstraction, using the driver that captured the payment (Stripe
     * for card payments, offline for cash-in-hand). Records a refund
     * transaction on the parent order linked to the sub-order via
     * meta — claiming the gateway's own record when it created one —
     * then flips the sub-order to Refunded or PartiallyRefunded.
     *
     * @param  int|null  $amount  Amount in cents; null refunds the remaining refundable balance
     *
     * @throws AuthorizationException when the actor may not manage the sub-order
     * @throws InvalidRestaurantOrderTransitionException when the sub-order is not refundable
     * @throws RefundExceedsRemainingAmountException when the amount exceeds the remaining balance
     * @throws PaymentNotCapturedException when the parent order has no captured payment
     * @throws PaymentRefundFailedException when the payment driver rejects the refund
     */
    public function handle(User $actor, RestaurantOrder $restaurantOrder, ?int $amount = null, ?string $notes = null): RestaurantOrder
    {
        throw_unless($actor->can('refund', $restaurantOrder), AuthorizationException::class);

        $from = $restaurantOrder->status;

        throw_unless(
            $from->isRefundable(),
            InvalidRestaurantOrderTransitionException::fromTo($from, RestaurantOrderStatus::Refunded),
        );

        $order = $restaurantOrder->order;

        $remaining = $restaurantOrder->total - $this->refundedAmount($restaurantOrder);
        $amount ??= $remaining;

        throw_if(
            $amount <= 0 || $amount > $remaining,
            RefundExceedsRemainingAmountException::create($amount, $remaining),
        );

        /** @var Transaction|null $capture */
        $capture = $order->transactions()
            ->where('type', 'capture')
            ->where('success', true)
            ->latest('id')
            ->first();

        throw_if($capture === null, PaymentNotCapturedException::forOrder());

        /** @var PaymentTypeInterface $driver */
        $driver = Payments::driver($capture->driver);

        $lastRefundId = (int) $order->transactions()->where('type', 'refund')->max('id');

        $refund = $driver->order($order)->refund(
            $capture,
            $amount,
            $notes ?? "Refund for sub-order {$restaurantOrder->reference}",
        );

        throw_unless($refund->success, PaymentRefundFailedException::withReason($refund->message));

        $to = $amount === $remaining ? RestaurantOrderStatus::Refunded : RestaurantOrderStatus::PartiallyRefunded;

        DB::transaction(function () use ($order, $capture, $amount, $notes, $restaurantOrder, $to, $lastRefundId): void {
            $recorded = $order->transactions()
                ->where('type', 'refund')
                ->where('id', '>', $lastRefundId)
                ->latest('id')
                ->first();

            if ($recorded) {
                // Gateways like Stripe record their own refund
                // transaction; claim it for this sub-order instead
                // of recording a duplicate.
                $recorded->update([
                    'parent_transaction_id' => $capture->id,
                    'meta' => ['restaurant_order_id' => $restaurantOrder->id],
                ]);
            } else {
                $order->transactions()->create([
                    'parent_transaction_id' => $capture->id,
                    'success' => true,
                    'type' => 'refund',
                    'driver' => $capture->driver,
                    'amount' => $amount,
                    'reference' => 'RFD-'.Str::upper(Str::random(10)),
                    'status' => 'settled',
                    'notes' => $notes,
                    'card_type' => $capture->card_type,
                    'last_four' => $capture->last_four,
                    'meta' => ['restaurant_order_id' => $restaurantOrder->id],
                ]);
            }

            $restaurantOrder->update(['status' => $to]);
        });

        RestaurantOrderStatusChanged::dispatch($restaurantOrder->refresh(), $actor, $from, $to);

        return $restaurantOrder;
    }

    /**
     * Total already refunded against this sub-order, in cents, from
     * refund transactions on the parent order carrying its id in meta.
     */
    private function refundedAmount(RestaurantOrder $restaurantOrder): int
    {
        /** @var Collection<int, Transaction> $refunds */
        $refunds = $restaurantOrder->order->transactions()
            ->where('type', 'refund')
            ->where('success', true)
            ->where('meta->restaurant_order_id', $restaurantOrder->id)
            ->get();

        return (int) $refunds->sum(fn (Transaction $transaction): int => (int) $transaction->getRawOriginal('amount'));
    }
}
