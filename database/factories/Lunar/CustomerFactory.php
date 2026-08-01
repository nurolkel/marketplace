<?php

namespace Database\Factories\Lunar;

use App\Models\Lunar\Customer;
use Lunar\Database\Factories\CustomerFactory as BaseCustomerFactory;

class CustomerFactory extends BaseCustomerFactory
{
    protected $model = Customer::class;
}
