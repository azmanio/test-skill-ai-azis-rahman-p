<?php

use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('catalog');
});

Route::get('/catalog', [CheckoutController::class, 'showCatalog'])->name('catalog');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('web.checkout');
Route::get('/orders/{orderNumber}', [CheckoutController::class, 'showOrder'])->name('orders.show');