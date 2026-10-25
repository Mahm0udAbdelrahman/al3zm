<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Dashboard\AuthController;
use App\Http\Controllers\Dashboard\HomeController;
use App\Http\Controllers\Dashboard\RoleController;
use App\Http\Controllers\Dashboard\UserController;
use App\Http\Controllers\Dashboard\NotificationController;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use App\Http\Controllers\Dashboard\SendNotificationController;

Route::group(
    [
        'prefix'     => LaravelLocalization::setLocale(),
        'middleware' => ['localeSessionRedirect', 'localizationRedirect', 'localeViewPath'],
    ], function () {

        Route::get('/admin/login', [AuthController::class, 'login'])->name('login');
        Route::post('/admin/login', [AuthController::class, 'loginAction'])->name('loginAction');

        Route::group(['middleware' => ['auth', 'notification', 'is_role'], 'prefix' => 'admin', 'as' => 'Admin.'], function () {
            // home
            Route::get('/home', [HomeController::class, 'index'])->name('home');
            Route::get('/delete/{model}/{id}', [HomeController::class, 'confirmDelete'])->name('confirmDelete');

            // roles
            Route::resource('roles', RoleController::class);

            // users
            Route::resource('users', UserController::class);
            Route::post('/users/bulk-delete', [UserController::class, 'bulkDelete'])
                ->name('users.bulkDelete');

            Route::get('/profile', [UserController::class, 'profile'])->name('profile');
            Route::put('/profile', [UserController::class, 'updateProfile'])->name('updateProfile');

            // notifications
            Route::get('/notifications', [NotificationController::class, 'getNotifications'])->name('notifications');
            Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
            Route::get('/notifications/read-all', [NotificationController::class, 'ReadAll'])->name('notifications.markAllRead');
            Route::post('/notifications/read', [NotificationController::class, 'markAsRead'])->name('notifications.markAsRead');

            // send_notifications
            Route::get('send_notifications', [SendNotificationController::class, 'index'])->name('send_notifications.index');
            Route::get('send_notifications/create', [SendNotificationController::class, 'create'])->name('send_notifications.create');
            Route::post('send_notifications', [SendNotificationController::class, 'store'])->name('send_notifications.store');
            Route::delete('send_notifications/{id}', [SendNotificationController::class, 'destroy'])->name('send_notifications.destroy');
            // logout
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');

        });
    });
Route::get('/notifications/count', function () {
    return response()->json([
        'unread_count' => auth()->user()->unreadNotifications()->count(),
    ]);
})->name('notifications.count');
