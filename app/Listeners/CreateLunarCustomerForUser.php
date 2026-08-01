<?php

namespace App\Listeners;

use App\Actions\Customers\GetOrCreateCustomerAction;
use App\Models\User;
use Illuminate\Auth\Events\Registered;

class CreateLunarCustomerForUser
{
    public function __construct(
        private GetOrCreateCustomerAction $action,
    ) {}

    /**
     * Give every newly registered user a storefront customer record.
     */
    public function handle(Registered $event): void
    {
        if ($event->user instanceof User) {
            $this->action->handle($event->user);
        }
    }
}
