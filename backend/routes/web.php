<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ReturnRequestController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\PaymentCallbackController;
use Illuminate\Support\Facades\Route;

// Route based redirect keeps the app subfolder in the URL.
Route::get('/', fn () => redirect()->route('admin.dashboard'));

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.submit');

    Route::middleware('admin')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::resource('categories', CategoryController::class)->except('show');
        Route::resource('products', ProductController::class)->except('show');
        Route::post('products/{product}/toggle-active', [ProductController::class, 'toggleActive'])->name('products.toggle-active');
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
        Route::get('returns', [ReturnRequestController::class, 'index'])->name('returns.index');
        Route::get('returns/{returnRequest}', [ReturnRequestController::class, 'show'])->name('returns.show');
        Route::post('returns/{returnRequest}/approve', [ReturnRequestController::class, 'approve'])->name('returns.approve');
        Route::post('returns/{returnRequest}/reject', [ReturnRequestController::class, 'reject'])->name('returns.reject');
        Route::post('returns/{returnRequest}/mark-returned', [ReturnRequestController::class, 'markReturned'])->name('returns.mark-returned');
        Route::post('returns/{returnRequest}/refund', [ReturnRequestController::class, 'refund'])->name('returns.refund');
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users/{user}/logout-everywhere', [UserController::class, 'logoutEverywhere'])->name('users.logout-everywhere');
        Route::post('users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
});

// Laravel redirects guests to a route named "login".
Route::get('login', fn () => redirect()->route('admin.login'))->name('login');

// Razorpay sends the customer back here after the hosted payment page.
Route::get('payment/callback', [PaymentCallbackController::class, 'handle']);
