<?php

return [

    'default' => env('PAYMENTS_TYPE', 'card'),

    'types' => [
        'card' => [
            'driver' => 'stripe',
        ],
        'cash-in-hand' => [
            'driver' => 'offline',
            'authorized' => 'payment-offline',
        ],
    ],

];
