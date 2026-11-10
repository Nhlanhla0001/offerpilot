<?php

use App\Http\Controllers\Seller\DashboardController;
use App\Http\Controllers\Seller\InventoryController;
use App\Http\Controllers\Seller\NotificationsController;
use App\Http\Controllers\Seller\OffersController;
use App\Http\Controllers\Seller\OrdersController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\CommerceCheckoutController;
use App\Http\Controllers\Storefront\NegotiationController;
use App\Http\Controllers\Storefront\ProductController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/store')->name('home');

Route::prefix('store')->group(function () {
    Route::get('/', [ProductController::class, 'index'])->name('store.index');
    Route::get('{product:sku}', [ProductController::class, 'show'])->name('store.show');
});

Route::get('checkout', [CommerceCheckoutController::class, 'index'])->name('checkout.index');
Route::post('checkout', [CommerceCheckoutController::class, 'store'])->name('checkout.store');
Route::post('checkout/complete', [CommerceCheckoutController::class, 'capture'])->name('checkout.complete');

Route::prefix('cart')->group(function () {
    Route::get('/', [CartController::class, 'show'])->name('cart.show');
    Route::post('items', [CartController::class, 'store'])->name('cart.items.store');
    Route::patch('items/{item}', [CartController::class, 'update'])->name('cart.items.update');
    Route::delete('items/{item}', [CartController::class, 'destroy'])->name('cart.items.destroy');
});

Route::post('store/{product:sku}/negotiate', NegotiationController::class)
    ->middleware('throttle:buyer-negotiate')
    ->name('store.negotiate');
Route::post('store/{product:sku}/buy', [CheckoutController::class, 'buy'])
    ->middleware('throttle:buyer-checkout')
    ->name('store.buy');
Route::post('store/{product:sku}/checkout', [CheckoutController::class, 'create'])
    ->middleware('throttle:buyer-checkout')
    ->name('store.checkout');
Route::post('checkout/capture', [CheckoutController::class, 'capture'])
    ->middleware('throttle:buyer-checkout')
    ->name('checkout.capture');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('notifications', [NotificationsController::class, 'index'])->name('notifications.index');
    Route::get('offers', [OffersController::class, 'index'])->name('offers.index');
    Route::get('orders', [OrdersController::class, 'index'])->name('orders.index');

    Route::prefix('inventory')->group(function () {
        Route::get('/', [InventoryController::class, 'index'])->name('inventory.index');
        Route::get('create', [InventoryController::class, 'create'])->name('inventory.create');
        Route::post('/', [InventoryController::class, 'store'])->name('inventory.store');
        Route::post('setup', [InventoryController::class, 'storeSetup'])->name('inventory.setup');
        Route::get('{product}/edit', [InventoryController::class, 'edit'])->name('inventory.edit');
        Route::patch('{product}', [InventoryController::class, 'update'])->name('inventory.update');
    });

});

require __DIR__.'/settings.php';
