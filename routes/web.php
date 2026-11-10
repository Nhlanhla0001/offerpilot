<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\NegotiationController;
use App\Http\Controllers\Storefront\ProductController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/store')->name('home');

Route::prefix('store')->group(function () {
    Route::get('/', [ProductController::class, 'index'])->name('store.index');
    Route::get('{product:sku}', [ProductController::class, 'show'])->name('store.show');
});

Route::prefix('cart')->group(function () {
    Route::get('/', [CartController::class, 'show'])->name('cart.show');
    Route::post('items', [CartController::class, 'store'])->name('cart.items.store');
    Route::patch('items/{item}', [CartController::class, 'update'])->name('cart.items.update');
    Route::delete('items/{item}', [CartController::class, 'destroy'])->name('cart.items.destroy');
});

Route::middleware(['auth'])->group(function () {
    Route::post('store/{product:sku}/negotiate', NegotiationController::class)->name('store.negotiate');
    Route::post('store/{product:sku}/buy', [CheckoutController::class, 'buy'])->name('store.buy');
    Route::post('store/{product:sku}/checkout', [CheckoutController::class, 'create'])->name('store.checkout');
    Route::post('checkout/capture', [CheckoutController::class, 'capture'])->name('checkout.capture');
});

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
