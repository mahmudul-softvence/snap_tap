<?php

use App\Http\Controllers\Frontend\Auth\RegisterController;
use App\Http\Controllers\Frontend\Auth\SocialiteController;
use App\Http\Controllers\Frontend\QrController;
use App\Http\Controllers\MessageTemplateController;
use App\Http\Controllers\Frontend\UserProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Frontend\PaymentMethodController;
use App\Http\Controllers\Frontend\PlanController;
use App\Http\Controllers\Frontend\SubscriptionController;



Route::middleware('guest:sanctum')->group(function () {

    Route::controller(SocialiteController::class)->group(function () {
        Route::get('/auth/{provider}/redirect', 'redirect');
        Route::get('/auth/{provider}/callback', 'callback');
    });

    Route::controller(RegisterController::class)->group(function () {
        Route::post('register', 'register');
        Route::post('login', 'login');
    });
});


Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [RegisterController::class, 'logout']);

    // Create Qr
    Route::controller(QrController::class)->group(function () {
        Route::get('get_qr/{provider}', 'get_qr');
        Route::post('store_qr/{provider}', 'store_qr');
    });

    // Message Template
    Route::controller(MessageTemplateController::class)->group(function () {
        Route::get('message_template', 'index');
        Route::post('message_template/create', 'create');
        Route::get('message_template/show/{id}', 'show');
        Route::put('message_template/update/{id}', 'update');
        Route::delete('message_template/delete/{id}', 'destroy');
    });
});



Route::middleware('auth:sanctum')->group(function () {
    Route::get('user-profile/show', [UserProfileController::class, 'showProfile']);
    Route::put('user-profile/update', [UserProfileController::class, 'update']);
});


Route::middleware('auth:sanctum')->group(function () {
    // Payment Methods
    Route::prefix('payment-methods')->group(function () {
        Route::get('/', [PaymentMethodController::class, 'index']);
        Route::post('/', [PaymentMethodController::class, 'store']);
        Route::put('/{id}/default', [PaymentMethodController::class, 'setDefault']);
        Route::delete('/{id}', [PaymentMethodController::class, 'destroy']);
    });

    // Plans
    Route::get('/plans', [PlanController::class, 'index']);
    
    // Subscriptions for user
    Route::prefix('subscriptions')->group(function () {
        Route::get('/', [SubscriptionController::class, 'show']);
        Route::post('/create-payment', [SubscriptionController::class, 'createPaymentIntent']);
        Route::post('/buynow', [SubscriptionController::class, 'buyNow']);
        Route::post('/swap', [SubscriptionController::class, 'swap']);
        Route::post('/cancel', [SubscriptionController::class, 'cancel']);
        Route::post('/resume', [SubscriptionController::class, 'resume']);
        Route::get('/invoices', [SubscriptionController::class, 'invoices']);
        Route::get('/invoice/{id}', [SubscriptionController::class, 'invoice']);

        Route::post('/start-trial', [SubscriptionController::class, 'startFreeTrial']);
        Route::post('/convert-trial', [SubscriptionController::class, 'convertTrialToPaid']);

        Route::post('/stripe/force-invoice', [SubscriptionController::class, 'forceInvoice']);
    });

});