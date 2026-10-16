<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CatalogController;
use App\Http\Controllers\Admin\DriverController;
use App\Http\Controllers\Admin\OperationsController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Middleware\StaffAccess;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('login', [AuthController::class, 'show'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.submit');
    Route::middleware(StaffAccess::class)->group(function () {
        Route::get('driver', [DriverController::class, 'index'])->name('driver.index');
        Route::get('driver/orders/{order}', [DriverController::class, 'show'])->name('driver.show');
        Route::post('driver/orders/{order}/status', [DriverController::class, 'status'])->name('driver.status');
        Route::post('driver/orders/{order}/collect', [DriverController::class, 'collect'])->name('driver.collect');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/', [OperationsController::class, 'dashboard'])->name('dashboard');
        Route::get('orders', [OperationsController::class, 'orders'])->name('orders');
        Route::get('kitchen', [OperationsController::class, 'kitchen'])->name('kitchen');
        Route::get('orders/{order}', [OperationsController::class, 'show'])->name('orders.show');
        Route::post('orders/{order}/status', [OperationsController::class, 'status'])->name('orders.status');
        Route::post('orders/{order}/collect', [OperationsController::class, 'collect'])->name('orders.collect');
        Route::get('account', [SettingsController::class, 'account'])->name('account');
        Route::post('account/password', [SettingsController::class, 'password'])->name('password');
        Route::middleware(StaffAccess::class.':manager')->group(function () {
            Route::post('orders/{order}/assign', [DriverController::class, 'assign'])->name('orders.assign');
            Route::get('products', [CatalogController::class, 'index'])->name('products');
            Route::get('products/create', [CatalogController::class, 'edit'])->name('products.create');
            Route::get('products/{product}', [CatalogController::class, 'edit'])->name('products.edit');
            Route::post('products/save/{product?}', [CatalogController::class, 'save'])->name('products.save');
            Route::post('products/{product}/variants', [CatalogController::class, 'variant'])->name('variants.save');
            Route::post('products/{product}/stock', [CatalogController::class, 'stock'])->name('stock.save');
            Route::post('products/{product}/images/{image}', [CatalogController::class, 'image'])->name('images.save');
            Route::get('categories', [CatalogController::class, 'categories'])->name('categories');
            Route::post('categories/{category?}', [CatalogController::class, 'category'])->name('categories.save');
            Route::get('coupons', [SettingsController::class, 'coupons'])->name('coupons');
            Route::post('coupons/{coupon?}', [SettingsController::class, 'coupon'])->name('coupons.save');
            Route::get('delivery', [SettingsController::class, 'delivery'])->name('delivery');
            Route::post('delivery/zones/{zone?}', [SettingsController::class, 'zone'])->name('zones.save');
            Route::post('delivery/slots/{slot?}', [SettingsController::class, 'slot'])->name('slots.save');
            Route::post('branches/{branch}', [SettingsController::class, 'branch'])->name('branches.save');
            Route::get('team', [SettingsController::class, 'team'])->name('team');
            Route::post('team/{staff?}', [SettingsController::class, 'staff'])->name('team.save');
        });
    });
});
