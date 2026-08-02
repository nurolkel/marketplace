<?php

use App\Enums\RestaurantOrderStatus;
use App\Events\RestaurantOrderStatusChanged;
use App\Models\Lunar\Order;
use App\Models\Restaurant;
use App\Models\RestaurantOrder;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Lunar\Base\DataTransferObjects\PaymentAuthorize;
use Lunar\Events\PaymentAttemptEvent;
use Lunar\Models\Currency;
use Lunar\Models\Language;

beforeEach(function () {
    Language::factory()->create(['code' => 'en', 'default' => true]);
    Currency::factory()->create(['default' => true]);

    $this->customer = User::factory()->create();
    $this->order = Order::factory()->create(['user_id' => $this->customer->id]);
});

test('pending sub-orders move to payment received when a payment authorizes', function () {
    $restaurant = Restaurant::factory()->create();
    $subA = RestaurantOrder::factory()->create([
        'order_id' => $this->order->id,
        'restaurant_id' => $restaurant->id,
        'status' => RestaurantOrderStatus::Pending,
    ]);
    $subB = RestaurantOrder::factory()->create([
        'order_id' => $this->order->id,
        'restaurant_id' => Restaurant::factory()->create()->id,
        'status' => RestaurantOrderStatus::Pending,
    ]);

    Event::fake([RestaurantOrderStatusChanged::class]);

    PaymentAttemptEvent::dispatch(new PaymentAuthorize(
        success: true, orderId: $this->order->id, paymentType: 'stripe',
    ));

    expect($subA->refresh()->status)->toBe(RestaurantOrderStatus::PaymentReceived)
        ->and($subA->placed_at)->not->toBeNull()
        ->and($subB->refresh()->status)->toBe(RestaurantOrderStatus::PaymentReceived);

    Event::assertDispatched(
        RestaurantOrderStatusChanged::class,
        fn (RestaurantOrderStatusChanged $event): bool => $event->to === RestaurantOrderStatus::PaymentReceived
            && $event->actor->is($this->customer)
    );
});

test('failed payment attempts leave sub-orders pending', function () {
    $subOrder = RestaurantOrder::factory()->create([
        'order_id' => $this->order->id,
        'status' => RestaurantOrderStatus::Pending,
    ]);

    PaymentAttemptEvent::dispatch(new PaymentAuthorize(
        success: false, message: 'Card declined', paymentType: 'stripe',
    ));

    expect($subOrder->refresh()->status)->toBe(RestaurantOrderStatus::Pending);
});

test('sub-orders past pending are not touched by the sync', function () {
    $subOrder = RestaurantOrder::factory()->create([
        'order_id' => $this->order->id,
        'status' => RestaurantOrderStatus::Preparing,
    ]);

    PaymentAttemptEvent::dispatch(new PaymentAuthorize(
        success: true, orderId: $this->order->id, paymentType: 'stripe',
    ));

    expect($subOrder->refresh()->status)->toBe(RestaurantOrderStatus::Preparing);
});
