<?php

namespace Tests\Support;

use Stripe\Checkout\Session;
use Stripe\StripeClient;

/**
 * Test double for the Stripe SDK boundary. Only the checkout sessions
 * API our actions use is faked; created payloads are recorded for
 * assertions. No HTTP ever leaves the process.
 */
class FakeStripeClient extends StripeClient
{
    public object $checkout;

    public function __construct()
    {
        $this->checkout = new class
        {
            public object $sessions;

            public function __construct()
            {
                $this->sessions = new class
                {
                    /** @var array<int, array<string, mixed>> */
                    public array $createdWith = [];

                    public Session $session;

                    public function __construct()
                    {
                        $this->session = Session::constructFrom([
                            'id' => 'cs_test_123',
                            'object' => 'checkout.session',
                            'url' => 'https://checkout.stripe.com/pay/cs_test_123',
                            'payment_intent' => 'pi_test_123',
                        ]);
                    }

                    /** @param array<string, mixed> $params */
                    public function create(array $params): Session
                    {
                        $this->createdWith[] = $params;

                        return $this->session;
                    }

                    public function retrieve(string $id): Session
                    {
                        return $this->session;
                    }
                };
            }
        };
    }

    public function __get($name)
    {
        return $this->{$name} ?? null;
    }
}
