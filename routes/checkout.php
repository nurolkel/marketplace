<?php

use App\Http\Controllers\Checkout\CartLineController;
use App\Http\Controllers\Checkout\CheckoutAddressController;
use App\Http\Controllers\Checkout\CheckoutSessionController;
use App\Http\Controllers\Checkout\PlaceOrderController;
use App\Http\Controllers\Checkout\ShippingOptionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('checkout')->name('checkout.')->group(function () {
    Route::post('lines', [CartLineController::class, 'store'])->name('lines.store');
    Route::delete('lines/{line}', [CartLineController::class, 'destroy'])->name('lines.destroy');
    Route::put('addresses', CheckoutAddressController::class)->name('addresses.update');
    Route::put('shipping-option', ShippingOptionController::class)->name('shipping-option.update');
    Route::post('session', CheckoutSessionController::class)->name('session.store');
    Route::post('place-order', PlaceOrderController::class)->name('place-order.store');
});
