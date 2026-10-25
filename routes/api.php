<?php

use App\Http\Controllers\Api\DeleteAccountController;
use App\Http\Controllers\Api\LoginController;
use App\Http\Controllers\Api\LogoutController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PasswordController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RegisterController;
use App\Http\Controllers\Api\Travel\ActivityController;
use App\Http\Controllers\Api\Travel\FlightController;
use App\Http\Controllers\Api\Travel\OrderController;
use App\Http\Controllers\Api\Travel\PaymentController;
use App\Http\Controllers\Api\Travel\StayController;
use App\Http\Controllers\Api\Travel\TravelController;
use Illuminate\Support\Facades\Route;

    // register
    Route::post("/register", [RegisterController::class, 'register']);

    // verify
    Route::post('/verify', [RegisterController::class, 'verify']);
    Route::post('/otp', [RegisterController::class, 'otp']);
    //login
    Route::post("/login", [LoginController::class, 'login']);
    //forget-password
    Route::post('/forget-password', [PasswordController::class, 'forgetPassword']);
    //confirmationOtp
    Route::post('/confirmation-otp', [PasswordController::class, 'confirmationOtp']);
    //reset-password
    Route::post('/reset-password', [PasswordController::class, 'resetPassword']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [ProfileController::class, 'profile']);
    Route::post('/profile', [ProfileController::class, 'updateProfile']);
    Route::post('/change-password', [PasswordController::class, 'changePassword']);
    Route::post('/logout', [LogoutController::class, 'logout']);
    Route::get('/notifications', [NotificationController::class, 'index']);

    Route::delete('/delete_account', [DeleteAccountController::class, 'deleteAccount']);
});

/*
|--------------------------------------------------------------------------
| Travel — /api/v1 (the paths the mobile app's Endpoints are written against)
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->middleware('lang')->group(function () {
    Route::get('/travel/providers', [TravelController::class, 'providers']);

    // Flights
    Route::get('/airports', [FlightController::class, 'airports']);
    Route::get('/flights/search-defaults', [FlightController::class, 'searchDefaults']);
    Route::post('/flights/search', [FlightController::class, 'search'])->middleware('throttle:30,1');
    Route::get('/flights/search/{searchId}', [FlightController::class, 'results']);
    Route::get('/flights/offers/{offerId}', [FlightController::class, 'offer']);
    Route::get('/flights/offers/{offerId}/seats', [FlightController::class, 'seatMap']);

    // Stays
    Route::get('/stays/cities', [StayController::class, 'cities']);
    Route::get('/stays/search-defaults', [StayController::class, 'searchDefaults']);
    Route::get('/stays/search', [StayController::class, 'search'])->middleware('throttle:30,1');
    Route::get('/stays/search/{searchId}', [StayController::class, 'results']);
    Route::get('/stays/{hotelId}', [StayController::class, 'show']);

    // Activities
    Route::get('/activities/cities', [ActivityController::class, 'cities']);
    Route::get('/activities', [ActivityController::class, 'index'])->middleware('throttle:30,1');
    Route::get('/activities/{activityId}', [ActivityController::class, 'show']);
    Route::get('/activities/{activityId}/availability', [ActivityController::class, 'availability']);

    // Payments — gateway callbacks are public; the result is always re-checked with the gateway.
    Route::get('/payments/gateways', [PaymentController::class, 'gateways']);
    Route::get('/payments/{payment}/return', [PaymentController::class, 'return'])->name('payments.return');
    Route::post('/payments/webhooks/{gateway}', [PaymentController::class, 'webhook'])->name('payments.webhook');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/orders', [OrderController::class, 'index']);
        Route::post('/orders', [OrderController::class, 'store']);
        Route::get('/orders/{order}', [OrderController::class, 'show']);
        Route::post('/orders/{order}/pay', [OrderController::class, 'pay']);
        Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
        Route::get('/payments/{payment}', [PaymentController::class, 'show']);
    });
});

