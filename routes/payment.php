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

Route::get('/test-payment-method', function () {
    \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

    $paymentMethod = \Stripe\PaymentMethod::create([
        'type' => 'card',
        'card' => [
            'token' => 'tok_mastercard',
        ],
    ]);

    return [
        'payment_method_id' => $paymentMethod->id,
        'message' => 'Use this payment_method_id in your subscription request'
    ];
});
