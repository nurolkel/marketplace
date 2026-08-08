<?php

use App\Enums\RestaurantOrderStatus;
use App\Models\Lunar\Customer;
use App\Models\Lunar\Order;
use App\Models\Lunar\Product;
use App\Models\Restaurant;
use App\Models\RestaurantOrder;
use App\Models\User;
use Lunar\Facades\CartSession;
use Lunar\Facades\Payments;
use Lunar\FieldTypes\Text;
use Lunar\Models\Channel;
use Lunar\Models\Country;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\Price;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Lunar\Models\TaxZone;
use Stripe\StripeClient;
use Tests\Support\FakeStripeClient;
use Tests\Support\FakeStripePaymentDriver;

beforeEach(function () {
    Language::factory()->create(['code' => 'en', 'default' => true]);
    Currency::factory()->create(['code' => 'USD', 'default' => true]);
    Channel::factory()->create(['default' => true]);
    TaxZone::factory()->create(['default' => true]);
    $this->country = Country::factory()->create();
    $this->user = User::factory()->create();

    $this->fakeStripe = new FakeStripeClient;
    $this->app->instance(StripeClient::class, $this->fakeStripe);

    $fakeDriver = new FakeStripePaymentDriver;
    $this->fakeDriver = $fakeDriver;
    Payments::extend('stripe', fn (): FakeStripePaymentDriver => $fakeDriver);
});

/**
 * A published product of the restaurant with a stocked, priced variant.
 */
function makePricedVariant(Restaurant $restaurant, string $name, int $price): ProductVariant
{
    $product = Product::factory()->create([
        'restaurant_id' => $restaurant->id,
        'attribute_data' => collect(['name' => new Text($name)]),
    ]);

    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'stock' => 50,
        // The factory attaches a tax class with rate amounts; use a
        // rate-free class so cart totals match the given price.
        'tax_class_id' => TaxClass::factory(),
    ]);

    Price::factory()->create([
        'priceable_type' => 'product_variant',
        'priceable_id' => $variant->id,
        'price' => $price,
        'currency_id' => Currency::getDefault()->id,
    ]);

    return $variant;
}

/**
 * A cart line payload pointing at the variant.
 *
 * @return array{purchasable_id: int, quantity: int}
 */
function linePayload(ProductVariant $variant, int $quantity = 1): array
{
    return ['purchasable_id' => $variant->id, 'quantity' => $quantity];
}

/**
 * A valid shipping address payload.
 *
 * @return array{shipping: array<string, mixed>}
 */
function addressPayload(): array
{
    return [
        'shipping' => [
            'first_name' => 'Nadia',
            'last_name' => 'Frost',
            'line_one' => '12 Icebox Lane',
            'city' => 'Portland',
            'state' => 'OR',
            'postcode' => '97201',
            'country_id' => Country::firstOrFail()->id,
            'contact_email' => 'nadia@example.com',
        ],
    ];
}

test('a line can be added to the cart', function () {
    $variant = makePricedVariant(Restaurant::factory()->create(), 'Lobster Ravioli', 2500);

    $response = $this->actingAs($this->user)->postJson(route('checkout.lines.store'), linePayload($variant, 2));

    $response->assertCreated()
        ->assertJsonPath('total', 5000)
        ->assertJsonPath('lines', 1);
});

test('guests can add lines to the cart', function () {
    $variant = makePricedVariant(Restaurant::factory()->create(), 'Lobster Ravioli', 2500);

    $this->postJson(route('checkout.lines.store'), linePayload($variant, 2))
        ->assertCreated()
        ->assertJsonPath('total', 5000)
        ->assertJsonPath('lines', 1);
});

test('guests must leave a contact email with their address', function () {
    $variant = makePricedVariant(Restaurant::factory()->create(), 'Margherita Pizza', 1800);
    $this->postJson(route('checkout.lines.store'), linePayload($variant));

    $payload = addressPayload();
    unset($payload['shipping']['contact_email']);

    $this->putJson(route('checkout.addresses.update'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['shipping.contact_email']);
});

test('account holders may skip the address contact email', function () {
    $variant = makePricedVariant(Restaurant::factory()->create(), 'Margherita Pizza', 1800);
    $this->actingAs($this->user)->postJson(route('checkout.lines.store'), linePayload($variant));

    $payload = addressPayload();
    unset($payload['shipping']['contact_email']);

    $this->actingAs($this->user)->putJson(route('checkout.addresses.update'), $payload)
        ->assertOk();
});

test('lines are validated', function () {
    $this->actingAs($this->user)
        ->postJson(route('checkout.lines.store'), ['purchasable_id' => 999, 'quantity' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['purchasable_id', 'quantity']);
});

test('a line can be removed from the cart', function () {
    $variant = makePricedVariant(Restaurant::factory()->create(), 'Margherita Pizza', 1800);

    $this->actingAs($this->user)->postJson(route('checkout.lines.store'), linePayload($variant));
    $lineId = CartSession::current()->lines->sole()->id;

    $this->actingAs($this->user)
        ->deleteJson(route('checkout.lines.destroy', $lineId))
        ->assertOk()
        ->assertJsonPath('lines', 0)
        ->assertJsonPath('total', 0);
});

test('checkout addresses are set and billing mirrors shipping', function () {
    $variant = makePricedVariant(Restaurant::factory()->create(), 'Margherita Pizza', 1800);
    $this->actingAs($this->user)->postJson(route('checkout.lines.store'), linePayload($variant));

    $this->actingAs($this->user)
        ->putJson(route('checkout.addresses.update'), addressPayload())
        ->assertOk()
        ->assertJsonStructure(['shipping_address_id', 'billing_address_id']);

    $cart = CartSession::current();
    expect($cart->shippingAddress->city)->toBe('Portland')
        ->and($cart->billingAddress->city)->toBe('Portland');
});

test('a stripe checkout session is created from the cart', function () {
    $variant = makePricedVariant(Restaurant::factory()->create(), 'Lobster Ravioli', 2500);
    $this->actingAs($this->user)->postJson(route('checkout.lines.store'), linePayload($variant, 2));

    $response = $this->actingAs($this->user)->postJson(route('checkout.session.store'));

    $response->assertCreated()
        ->assertJsonPath('url', 'https://checkout.stripe.com/pay/cs_test_123');

    $cart = CartSession::current();
    expect($cart->meta['checkout_session'])->toBe('cs_test_123')
        ->and($cart->meta['payment_intent'])->toBe('pi_test_123');

    $payload = $this->fakeStripe->checkout->sessions->createdWith[0];
    expect($payload['mode'])->toBe('payment')
        ->and($payload['customer_email'])->toBe($this->user->email)
        ->and($payload['line_items'][0]['quantity'])->toBe(2)
        ->and($payload['line_items'][0]['price_data']['unit_amount'])->toBe(2500)
        ->and($payload['line_items'][0]['price_data']['currency'])->toBe('USD');
});

test('an empty cart cannot start a checkout session', function () {
    $this->actingAs($this->user)
        ->postJson(route('checkout.session.store'))
        ->assertUnprocessable();
});

test('placing the order splits it per restaurant and marks sub-orders paid', function () {
    $restaurantA = Restaurant::factory()->create();
    $restaurantB = Restaurant::factory()->create();
    $variantA = makePricedVariant($restaurantA, 'Lobster Ravioli', 2500);
    $variantB = makePricedVariant($restaurantB, 'Margherita Pizza', 1500);

    $this->actingAs($this->user)->postJson(route('checkout.lines.store'), linePayload($variantA, 2));
    $this->actingAs($this->user)->postJson(route('checkout.lines.store'), linePayload($variantB));
    $this->actingAs($this->user)->putJson(route('checkout.addresses.update'), addressPayload());
    $this->actingAs($this->user)->putJson(route('checkout.shipping-option.update'), ['identifier' => 'standard']);
    $this->actingAs($this->user)->postJson(route('checkout.session.store'));

    $response = $this->actingAs($this->user)
        ->postJson(route('checkout.place-order.store'), ['session_id' => 'cs_test_123']);

    $response->assertCreated()->assertJsonStructure(['order_id', 'reference']);

    $order = Order::findOrFail($response->json('order_id'));
    expect($order->user_id)->toBe($this->user->id);

    // Two restaurant sub-orders plus the marketplace-owned sub-order
    // carrying the non-variant shipping line.
    $subOrders = RestaurantOrder::where('order_id', $order->id)->get();
    expect($subOrders)->toHaveCount(3)
        ->and($subOrders->whereNotNull('restaurant_id')->pluck('restaurant_id')->sort()->values()->all())
        ->toBe(collect([$restaurantA->id, $restaurantB->id])->sort()->values()->all())
        ->and($subOrders->whereNull('restaurant_id'))->toHaveCount(1)
        ->and($subOrders->every(
            fn (RestaurantOrder $subOrder): bool => $subOrder->status === RestaurantOrderStatus::PaymentReceived
                && $subOrder->placed_at !== null
        ))->toBeTrue();

    expect(CartSession::current())->toBeNull();
});

test('a guest can complete the full checkout flow', function () {
    $restaurant = Restaurant::factory()->create();
    $variant = makePricedVariant($restaurant, 'Lobster Ravioli', 2500);

    $this->postJson(route('checkout.lines.store'), linePayload($variant, 2));
    $this->putJson(route('checkout.addresses.update'), addressPayload());
    $this->putJson(route('checkout.shipping-option.update'), ['identifier' => 'standard']);
    $this->postJson(route('checkout.session.store'));

    expect($this->fakeStripe->checkout->sessions->createdWith[0]['customer_email'])
        ->toBe('nadia@example.com');

    $response = $this->postJson(route('checkout.place-order.store'), ['session_id' => 'cs_test_123']);

    $response->assertCreated()->assertJsonStructure(['order_id', 'reference']);

    $order = Order::findOrFail($response->json('order_id'));
    expect($order->user_id)->toBeNull()
        ->and($order->customer_id)->not->toBeNull()
        ->and($order->customer->meta['email'])->toBe('nadia@example.com')
        ->and($order->customer->fullName())->toBe('Nadia Frost');

    expect(RestaurantOrder::where('order_id', $order->id)->where('restaurant_id', $restaurant->id)->exists())
        ->toBeTrue();
});

test('a returning guest reuses their customer record', function () {
    $variant = makePricedVariant(Restaurant::factory()->create(), 'Lobster Ravioli', 2500);

    $this->postJson(route('checkout.lines.store'), linePayload($variant))->assertCreated();
    $this->putJson(route('checkout.addresses.update'), addressPayload())->assertOk();
    $this->putJson(route('checkout.shipping-option.update'), ['identifier' => 'standard'])->assertOk();
    $this->postJson(route('checkout.session.store'))->assertCreated();
    $this->postJson(route('checkout.place-order.store'), ['session_id' => 'cs_test_123'])->assertCreated();

    $this->postJson(route('checkout.lines.store'), linePayload($variant))->assertCreated();
    $this->putJson(route('checkout.addresses.update'), addressPayload())->assertOk();
    $this->putJson(route('checkout.shipping-option.update'), ['identifier' => 'standard'])->assertOk();
    $this->postJson(route('checkout.session.store'))->assertCreated();
    $this->postJson(route('checkout.place-order.store'), ['session_id' => 'cs_test_123'])->assertCreated();

    expect(Customer::query()->where('meta->email', 'nadia@example.com')->count())->toBe(1)
        ->and(Order::count())->toBe(2)
        ->and(Order::pluck('customer_id')->unique())->toHaveCount(1);
});

test('a declined payment places no order', function () {
    $this->fakeDriver->succeeds = false;

    $variant = makePricedVariant(Restaurant::factory()->create(), 'Lobster Ravioli', 2500);
    $this->actingAs($this->user)->postJson(route('checkout.lines.store'), linePayload($variant));
    $this->actingAs($this->user)->postJson(route('checkout.session.store'));

    $this->actingAs($this->user)
        ->postJson(route('checkout.place-order.store'), ['session_id' => 'cs_test_123'])
        ->assertStatus(402);

    expect(Order::count())->toBe(0);
});
