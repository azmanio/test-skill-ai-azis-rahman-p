<?php

use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::post('/checkout', [CheckoutController::class, 'checkout'])->name('checkout');
Route::post('/mock-payments', [PaymentController::class, 'process'])->name('mock-payments');