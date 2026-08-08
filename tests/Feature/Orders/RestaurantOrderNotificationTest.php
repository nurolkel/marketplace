<?php

use App\Actions\Orders\CancelRestaurantOrderAction;
use App\Actions\Orders\PauseRestaurantOrderAction;
use App\Actions\Orders\RefundRestaurantOrderAction;
use App\Actions\Orders\ResumeRestaurantOrderAction;
use App\Actions\Orders\TransitionRestaurantOrderAction;
use App\Enums\RestaurantOrderStatus;
use App\Enums\RestaurantRole;
use App\Models\Lunar\Order;
use App\Models\Restaurant;
use App\Models\RestaurantOrder;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\RestaurantOrderStatusChangedNotification;
use Illuminate\Support\Facades\Notification;
use Lunar\Models\Country;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\OrderAddress;
use Lunar\Models\Transaction;

beforeEach(function () {
    Language::factory()->create(['code' => 'en', 'default' => true]);
    Currency::factory()->create(['default' => true]);

    $this->restaurant = Restaurant::factory()->create();
    $this->owner = User::factory()->create();
    $this->staff = User::factory()->create();
    $this->restaurant->members()->attach($this->owner, ['role' => RestaurantRole::Owner->value]);
    $this->restaurant->members()->attach($this->staff, ['role' => RestaurantRole::Staff->value]);

    $this->customer = User::factory()->create();

    $this->makeSubOrder = function (RestaurantOrderStatus $status): RestaurantOrder {
        $order = Order::factory()->create(['user_id' => $this->customer->id]);

        return RestaurantOrder::factory()->create([
            'order_id' => $order->id,
            'restaurant_id' => $this->restaurant->id,
            'status' => $status,
            'paused_from_status' => $status === RestaurantOrderStatus::OnHold ? 'preparing' : null,
            'total' => 5000,
        ]);
    };
});

test('the customer is told when their sub-order is paused, with the reason', function () {
    $subOrder = ($this->makeSubOrder)(RestaurantOrderStatus::Preparing);

    Notification::fake();

    (new PauseRestaurantOrderAction)->handle($this->staff, $subOrder, 'Freezer breakdown');

    Notification::assertSentTo(
        $this->customer,
        RestaurantOrderStatusChangedNotification::class,
        fn (RestaurantOrderStatusChangedNotification $notification): bool => $notification->to === RestaurantOrderStatus::OnHold
            && $notification->reason === 'Freezer breakdown'
            && $notification->via($this->customer) === ['mail', 'database']
    );
});

test('the customer is told when their sub-order resumes', function () {
    $subOrder = ($this->makeSubOrder)(RestaurantOrderStatus::OnHold);

    Notification::fake();

    (new ResumeRestaurantOrderAction)->handle($this->staff, $subOrder);

    Notification::assertSentTo(
        $this->customer,
        RestaurantOrderStatusChangedNotification::class,
        fn (RestaurantOrderStatusChangedNotification $notification): bool => $notification->from === RestaurantOrderStatus::OnHold
            && $notification->to === RestaurantOrderStatus::Preparing
    );
});

test('the customer is told when their sub-order is dispatched', function () {
    $subOrder = ($this->makeSubOrder)(RestaurantOrderStatus::Preparing);

    Notification::fake();

    (new TransitionRestaurantOrderAction)->handle($this->staff, $subOrder, RestaurantOrderStatus::Dispatched);

    Notification::assertSentTo(
        $this->customer,
        RestaurantOrderStatusChangedNotification::class,
        fn (RestaurantOrderStatusChangedNotification $notification): bool => $notification->to === RestaurantOrderStatus::Dispatched
    );
});

test('the customer is told as their order moves through fulfilment', function (RestaurantOrderStatus $from, RestaurantOrderStatus $to) {
    $subOrder = ($this->makeSubOrder)($from);

    Notification::fake();

    (new TransitionRestaurantOrderAction)->handle($this->staff, $subOrder, $to);

    Notification::assertSentTo(
        $this->customer,
        RestaurantOrderStatusChangedNotification::class,
        fn (RestaurantOrderStatusChangedNotification $notification): bool => $notification->to === $to
    );
})->with([
    'payment received' => [RestaurantOrderStatus::Pending, RestaurantOrderStatus::PaymentReceived],
    'accepted' => [RestaurantOrderStatus::PaymentReceived, RestaurantOrderStatus::Accepted],
    'preparing' => [RestaurantOrderStatus::Accepted, RestaurantOrderStatus::Preparing],
    'completed' => [RestaurantOrderStatus::Dispatched, RestaurantOrderStatus::Completed],
]);

test('notifications follow the customer channel preference', function (string $preference, ?string $phone, array $expected) {
    $customer = User::factory()->create([
        'notification_channel' => $preference,
        'phone' => $phone,
    ]);

    $notification = new RestaurantOrderStatusChangedNotification(
        ($this->makeSubOrder)(RestaurantOrderStatus::Preparing),
        RestaurantOrderStatus::Preparing,
        RestaurantOrderStatus::Dispatched,
    );

    expect($notification->via($customer))->toBe($expected);
})->with([
    'mail' => ['mail', null, ['mail', 'database']],
    'sms with phone' => ['sms', '+15551234567', [SmsChannel::class, 'database']],
    'sms without phone falls back to mail' => ['sms', null, ['mail', 'database']],
    'both with phone' => ['both', '+15551234567', ['mail', SmsChannel::class, 'database']],
    'both without phone falls back to mail' => ['both', null, ['mail', 'database']],
]);

test('guests get order updates by on-demand mail to their contact email', function () {
    $country = Country::factory()->create();
    $order = Order::factory()->create(['user_id' => null]);
    OrderAddress::create([
        'order_id' => $order->id,
        'type' => 'billing',
        'first_name' => 'Nadia',
        'last_name' => 'Frost',
        'line_one' => '12 Icebox Lane',
        'city' => 'Portland',
        'postcode' => '97201',
        'country_id' => $country->id,
        'contact_email' => 'nadia@example.com',
    ]);

    $subOrder = RestaurantOrder::factory()->create([
        'order_id' => $order->id,
        'restaurant_id' => $this->restaurant->id,
        'status' => RestaurantOrderStatus::Preparing,
    ]);

    Notification::fake();

    (new TransitionRestaurantOrderAction)->handle($this->staff, $subOrder, RestaurantOrderStatus::Dispatched);

    Notification::assertSentOnDemand(
        RestaurantOrderStatusChangedNotification::class,
        fn (RestaurantOrderStatusChangedNotification $notification, array $channels, object $notifiable): bool => $notifiable->routes['mail'] === 'nadia@example.com'
            && $notification->to === RestaurantOrderStatus::Dispatched
    );
});

test('the customer is told about refunds', function () {
    $subOrder = ($this->makeSubOrder)(RestaurantOrderStatus::PaymentReceived);

    Transaction::factory()->create([
        'order_id' => $subOrder->order_id,
        'type' => 'capture',
        'success' => true,
        'amount' => 5000,
        'driver' => 'offline',
    ]);

    Notification::fake();

    (new RefundRestaurantOrderAction)->handle($this->staff, $subOrder, 2000);

    Notification::assertSentTo(
        $this->customer,
        RestaurantOrderStatusChangedNotification::class,
        fn (RestaurantOrderStatusChangedNotification $notification): bool => $notification->to === RestaurantOrderStatus::PartiallyRefunded
    );
});

test('staff are alerted when the customer cancels, and the customer is told too', function () {
    $subOrder = ($this->makeSubOrder)(RestaurantOrderStatus::Accepted);

    Notification::fake();

    app(CancelRestaurantOrderAction::class)->handle($this->customer, $subOrder, 'Ordered by mistake');

    Notification::assertSentTo(
        $this->customer,
        RestaurantOrderStatusChangedNotification::class,
        fn (RestaurantOrderStatusChangedNotification $notification): bool => $notification->to === RestaurantOrderStatus::Cancelled
            && $notification->reason === 'Ordered by mistake'
    );
    Notification::assertSentTo($this->owner, RestaurantOrderStatusChangedNotification::class);
    Notification::assertSentTo($this->staff, RestaurantOrderStatusChangedNotification::class);
});

test('staff are not alerted when a staff member cancels themselves', function () {
    $subOrder = ($this->makeSubOrder)(RestaurantOrderStatus::Preparing);

    Notification::fake();

    app(CancelRestaurantOrderAction::class)->handle($this->staff, $subOrder, 'Out of stock');

    Notification::assertSentTo($this->customer, RestaurantOrderStatusChangedNotification::class);
    Notification::assertNotSentTo($this->owner, RestaurantOrderStatusChangedNotification::class);
});
