<?php

use App\Http\Controllers\Api\LoginController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PasswordController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Support\Facades\Route;

    // register
    Route::post("/register", [LoginController::class, 'register']);
    //login
    Route::post("/login", [LoginController::class, 'login']);
    // verify
    Route::post('/verify', [LoginController::class, 'verify']);

    //forget-password
    Route::post('/forget-password', [PasswordController::class, 'forgetPassword']);
    //confirmationOtp
    Route::post('/confirmation-otp', [PasswordController::class, 'confirmationOtp']);
    //reset-password
    Route::post('/reset-password', [PasswordController::class, 'resetPassword']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [ProfileController::class, 'profile']);
    Route::post('/profile', [ProfileController::class, 'updateProfile']);
    Route::post('/change-password', [ProfileController::class, 'changePassword']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    
    Route::delete('/delete_account', [ProfileController::class, 'deleteAccount']);
    Route::post('/logout', [ProfileController::class, 'logout']);
});



