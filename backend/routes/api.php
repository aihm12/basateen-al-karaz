<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DeviceTokenController;
use App\Http\Controllers\FirebaseAuthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Middleware\ActiveCustomer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('auth/send-otp', [AuthController::class, 'send'])->middleware('throttle:otp-send');
Route::post('auth/verify-otp', [AuthController::class, 'verify'])->middleware('throttle:otp-verify');
Route::post('auth/firebase', FirebaseAuthController::class)->middleware('throttle:otp-verify');
Route::middleware('throttle:api')->group(function () {
    Route::get('payment-config', [PaymentController::class, 'config']);
    Route::get('categories', [CatalogController::class, 'categories']);
    Route::get('products', [CatalogController::class, 'products']);
    Route::get('products/{id}', [CatalogController::class, 'product'])->whereNumber('id');
    Route::get('branches', [CatalogController::class, 'branches']);
    Route::get('delivery-slots', [CatalogController::class, 'slots']);
    Route::middleware(['auth:sanctum', ActiveCustomer::class])->group(function () {
        Route::get('me', fn (Request $r) => $r->user());
        Route::put('device-token', [DeviceTokenController::class, 'store']);
        Route::delete('device-token', [DeviceTokenController::class, 'destroy']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('addresses', [AccountController::class, 'addresses']);
        Route::post('addresses', [AccountController::class, 'addAddress']);
        Route::get('favorites', [AccountController::class, 'favorites']);
        Route::put('favorites/{id}', [AccountController::class, 'favorite'])->whereNumber('id');
        Route::delete('favorites/{id}', [AccountController::class, 'unfavorite'])->whereNumber('id');
        Route::get('cart', [CartController::class, 'show']);
        Route::delete('cart', [CartController::class, 'clear']);
        Route::post('cart/items', [CartController::class, 'add']);
        Route::patch('cart/items/{id}', [CartController::class, 'update'])->whereNumber('id');
        Route::delete('cart/items/{id}', [CartController::class, 'remove'])->whereNumber('id');
        Route::get('orders', [OrderController::class, 'index']);
        Route::post('orders', [OrderController::class, 'store']);
        Route::get('orders/{id}', [OrderController::class, 'show'])->whereNumber('id');
        Route::post('orders/{id}/payment-session', [PaymentController::class, 'session'])->whereNumber('id');
        Route::post('orders/{id}/verify-payment', [PaymentController::class, 'verify'])->whereNumber('id');
    });
});
