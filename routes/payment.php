<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Backend\PaymentMethodController;

Route::middleware('auth:sanctum')->group(function () {

    // Payment Methods
    Route::prefix('payment-methods')->group(function () {
        Route::get('/', [PaymentMethodController::class, 'index']);
        Route::post('/', [PaymentMethodController::class, 'store']);
        Route::put('/{id}/default', [PaymentMethodController::class, 'setDefault']);
        Route::delete('/{id}', [PaymentMethodController::class, 'destroy']);
    });

});


