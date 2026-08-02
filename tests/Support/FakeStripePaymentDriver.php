<?php

namespace Tests\Support;

use Lunar\Base\DataTransferObjects\PaymentAuthorize;
use Lunar\Base\DataTransferObjects\PaymentCapture;
use Lunar\Base\DataTransferObjects\PaymentRefund;
use Lunar\Events\PaymentAttemptEvent;
use Lunar\Models\Contracts\Transaction as TransactionContract;
use Lunar\PaymentTypes\AbstractPayment;

/**
 * Test double for Lunar's payment-driver boundary, mimicking the real
 * Stripe driver's observable behavior: authorize creates the order and
 * dispatches the attempt event; refund records its own refund
 * transaction (without sub-order meta), like the gateway does.
 */
class FakeStripePaymentDriver extends AbstractPayment
{
    public bool $succeeds = true;

    public function authorize(): ?PaymentAuthorize
    {
        if (! $this->succeeds) {
            $failure = new PaymentAuthorize(success: false, message: 'Card declined', paymentType: 'stripe');

            PaymentAttemptEvent::dispatch($failure);

            return $failure;
        }

        $order = $this->cart->createOrder();

        $response = new PaymentAuthorize(success: true, orderId: $order->id, paymentType: 'stripe');

        PaymentAttemptEvent::dispatch($response);

        return $response;
    }

    public function refund(TransactionContract $transaction, int $amount = 0, $notes = null): PaymentRefund
    {
        $transaction->order->transactions()->create([
            'success' => true,
            'type' => 'refund',
            'driver' => 'stripe',
            'amount' => $amount,
            'reference' => 'pi_test_123',
            'status' => 'succeeded',
            'notes' => $notes,
            'card_type' => $transaction->card_type,
            'last_four' => $transaction->last_four,
        ]);

        return new PaymentRefund(success: true);
    }

    public function capture(TransactionContract $transaction, $amount = 0): PaymentCapture
    {
        return new PaymentCapture(success: true);
    }
}
