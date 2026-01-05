<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Backend\PlanController;
use App\Http\Controllers\Backend\SubscriptionController;

Route::middleware('auth:sanctum')->group(function () {
    // Plans
    Route::get('/plans', [PlanController::class, 'index']);
    
    // Subscriptions
    Route::prefix('subscriptions')->group(function () {
        Route::get('/', [SubscriptionController::class, 'show']);
        Route::post('/create', [SubscriptionController::class, 'store']);
        Route::post('/swap', [SubscriptionController::class, 'swap']);
        Route::post('/cancel', [SubscriptionController::class, 'cancel']);
        Route::post('/resume', [SubscriptionController::class, 'resume']);
        Route::get('/invoices', [SubscriptionController::class, 'invoices']);
        Route::get('/invoice/{id}', [SubscriptionController::class, 'invoice']);

        Route::post('/start-trial', [SubscriptionController::class, 'startFreeTrial']);
        Route::post('/convert-trial', [SubscriptionController::class, 'convertTrialToPaid']);
    });
    
});