<?php

use App\Http\Controllers\Admin\DeliveryAdminController;
use App\Http\Controllers\Admin\FloorAdminController;
use App\Http\Controllers\Admin\GalleryAdminController;
use App\Http\Controllers\Admin\MenuAdminController;
use App\Http\Controllers\Admin\MessageAdminController;
use App\Http\Controllers\Admin\OrderBoardController;
use App\Http\Controllers\Admin\ReservationAdminController;
use App\Http\Controllers\Admin\VoucherAdminController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CourierWebhookController;
use App\Http\Controllers\KitchenFeedController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\VoucherController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::post('/contact', [PageController::class, 'contactStore'])->middleware('throttle:5,10')->name('contact.store');
Route::get('/menu', [MenuController::class, 'index'])->name('menu');

Route::prefix('vouchers')->name('vouchers.')->group(function () {
    Route::get('/', [VoucherController::class, 'index'])->name('index');
    Route::post('purchase', [VoucherController::class, 'purchase'])->middleware('throttle:6,1')->name('start');
    Route::post('check', [VoucherController::class, 'check'])->middleware('throttle:20,1')->name('check');
    Route::get('purchase/{token}', [VoucherController::class, 'show'])->name('purchase');
    Route::get('purchase/{token}/status', [VoucherController::class, 'status'])->middleware('throttle:60,1')->name('purchase.status');
});

Route::prefix('cart')->name('cart.')->group(function () {
    Route::get('/', [CartController::class, 'show'])->name('show');
    Route::post('/', [CartController::class, 'add'])->middleware('throttle:60,1')->name('add');
    Route::patch('{key}', [CartController::class, 'update'])->where('key', '[a-f0-9]{32}')->name('update');
    Route::delete('{key}', [CartController::class, 'destroy'])->where('key', '[a-f0-9]{32}')->name('destroy');
});

Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout');
Route::post('/checkout/quote', [CheckoutController::class, 'quote'])->middleware('throttle:60,1')->name('checkout.quote');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');

Route::get('/kitchen/feed', KitchenFeedController::class)->middleware('throttle:60,1')->name('kitchen.feed');
Route::get('/track/{code}', [TrackingController::class, 'show'])->name('track');
Route::get('/track/{code}/receipt', [TrackingController::class, 'receipt'])->name('track.receipt');
Route::get('/track/{code}/receipt.pdf', [TrackingController::class, 'receiptPdf'])->name('track.receipt.pdf');
Route::post('/track/{code}/served', [TrackingController::class, 'served'])->middleware('throttle:20,1')->name('track.served');
Route::get('/track/{code}/status', [TrackingController::class, 'status'])->middleware('throttle:60,1')->name('track.status');

Route::post('/webhooks/stripe', StripeWebhookController::class)->name('webhooks.stripe');
Route::post('/webhooks/courier/{provider}', CourierWebhookController::class)->name('webhooks.courier');

Route::prefix('reservations')->name('reservations.')->group(function () {
    Route::get('/', [ReservationController::class, 'index'])->name('index');
    Route::get('availability', [ReservationController::class, 'availability'])->middleware('throttle:60,1')->name('availability');
    Route::post('/', [ReservationController::class, 'store'])->middleware('throttle:6,1')->name('store');
    Route::get('{code}', [ReservationController::class, 'show'])->name('show');
    Route::get('{code}/receipt', [ReservationController::class, 'receipt'])->name('receipt');
    Route::get('{code}/receipt.pdf', [ReservationController::class, 'receiptPdf'])->name('receipt.pdf');
    Route::post('{code}/cancel', [ReservationController::class, 'cancel'])->name('cancel');
});

Route::get('/dashboard', fn () => redirect()->route('admin.orders'))->middleware('auth')->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin,staff'])->group(function () {
    Route::get('/', fn () => redirect()->route('admin.orders'));

    Route::get('orders', [OrderBoardController::class, 'index'])->name('orders');
    Route::get('orders/feed', [OrderBoardController::class, 'feed'])->name('orders.feed');
    Route::get('orders/history', [OrderBoardController::class, 'history'])->name('orders.history');
    Route::post('orders/{order}/advance', [OrderBoardController::class, 'advance'])->name('orders.advance');
    Route::post('orders/{order}/cancel', [OrderBoardController::class, 'cancel'])->name('orders.cancel');
    Route::post('orders/{order}/paid', [OrderBoardController::class, 'markPaid'])->name('orders.paid');

    Route::post('orders/{order}/courier/dispatch', [DeliveryAdminController::class, 'dispatchCourier'])->name('orders.courier.dispatch');
    Route::post('orders/{order}/courier/cancel', [DeliveryAdminController::class, 'cancel'])->name('orders.courier.cancel');
    Route::post('orders/{order}/courier/driver', [DeliveryAdminController::class, 'assign'])->name('orders.courier.driver');
    Route::get('deliveries', [DeliveryAdminController::class, 'index'])->name('deliveries');

    Route::get('reservations', [ReservationAdminController::class, 'index'])->name('reservations');
    Route::post('reservations/{reservation}/status', [ReservationAdminController::class, 'status'])->name('reservations.status');

    Route::get('messages', [MessageAdminController::class, 'index'])->name('messages');
    Route::post('messages/{message}/read', [MessageAdminController::class, 'toggleRead'])->name('messages.read');
    Route::delete('messages/{message}', [MessageAdminController::class, 'destroy'])->name('messages.destroy');

    Route::middleware('role:admin')->group(function () {
        Route::get('menu', [MenuAdminController::class, 'index'])->name('menu');
        Route::post('menu/categories', [MenuAdminController::class, 'categoryStore'])->name('menu.categories.store');
        Route::put('menu/categories/{category}', [MenuAdminController::class, 'categoryUpdate'])->name('menu.categories.update');
        Route::delete('menu/categories/{category}', [MenuAdminController::class, 'categoryDestroy'])->name('menu.categories.destroy');

        Route::get('menu/items/create', [MenuAdminController::class, 'itemForm'])->name('menu.items.create');
        Route::post('menu/items', [MenuAdminController::class, 'itemSave'])->name('menu.items.store');
        Route::get('menu/items/{item}/edit', [MenuAdminController::class, 'itemForm'])->name('menu.items.edit');
        Route::put('menu/items/{item}', [MenuAdminController::class, 'itemSave'])->name('menu.items.update');
        Route::post('menu/items/{item}/toggle', [MenuAdminController::class, 'itemToggle'])->name('menu.items.toggle');
        Route::delete('menu/items/{item}', [MenuAdminController::class, 'itemDestroy'])->name('menu.items.destroy');

        Route::get('floor', [FloorAdminController::class, 'index'])->name('floor');
        Route::post('floor', [FloorAdminController::class, 'storeFloor'])->name('floor.store');
        Route::put('floor/{floor}/tables', [FloorAdminController::class, 'sync'])->name('floor.sync');

        Route::get('gallery', [GalleryAdminController::class, 'index'])->name('gallery');
        Route::post('gallery', [GalleryAdminController::class, 'store'])->name('gallery.store');
        Route::delete('gallery/{photo}', [GalleryAdminController::class, 'destroy'])->name('gallery.destroy');

        Route::get('vouchers', [VoucherAdminController::class, 'index'])->name('vouchers');
        Route::post('vouchers/discount', [VoucherAdminController::class, 'storeDiscount'])->name('vouchers.discount');
        Route::post('vouchers/gift', [VoucherAdminController::class, 'storeGift'])->name('vouchers.gift');
        Route::post('vouchers/{voucher}/toggle', [VoucherAdminController::class, 'toggle'])->name('vouchers.toggle');
    });
});

require __DIR__.'/auth.php';
