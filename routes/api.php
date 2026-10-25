<?php

use App\Http\Controllers\Api\DeleteAccountController;
use App\Http\Controllers\Api\LoginController;
use App\Http\Controllers\Api\LogoutController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PasswordController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RegisterController;
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



