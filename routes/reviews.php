<?php

use App\Http\Controllers\Reviews\PlatformReviewController;
use App\Http\Controllers\Reviews\RestaurantOrderReviewController;
use App\Http\Controllers\Reviews\RestaurantReviewController;
use App\Http\Controllers\Reviews\ReviewController;
use Illuminate\Support\Facades\Route;

// Reading reviews is public. Writing one requires an account, and
// for restaurants and sub-orders a completed purchase — eligibility
// is enforced by StoreReviewAction, not the routes.
Route::get('restaurants/{restaurant}/reviews', [RestaurantReviewController::class, 'index'])
    ->name('restaurants.reviews.index');

Route::middleware(['auth'])->group(function () {
    Route::post('restaurants/{restaurant}/reviews', [RestaurantReviewController::class, 'store'])
        ->name('restaurants.reviews.store');
    Route::post('restaurant-orders/{restaurantOrder}/review', [RestaurantOrderReviewController::class, 'store'])
        ->name('restaurant-orders.review.store');
    Route::post('platform/review', [PlatformReviewController::class, 'store'])
        ->name('platform.review.store');
    Route::delete('reviews/{review}', [ReviewController::class, 'destroy'])
        ->name('reviews.destroy');
});
