<?php

use App\Http\Controllers\Frontend\UserProfileController;
use App\Http\Controllers\SettingController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Backend\AdminPlanController;
use App\Http\Controllers\Backend\AdminSubscriptionController;


    Route::controller(UserProfileController::class)->group(function () {
        Route::put('admin-profile/update', 'adminProfileUpdate')->middleware(['role:super_admin','auth:sanctum']);
    });

    Route::middleware('role:super_admin','auth:sanctum')->group(function () {
        Route::get('/settings', [SettingController::class, 'index']);
        Route::put('/settings/update', [SettingController::class, 'updateSettings']);
    });

    //ADMIN PLAN AND SUBSCRIPTION.
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/adminplan', [AdminPlanController::class, 'index']);
        Route::post('/plan/create', [AdminPlanController::class, 'store']);
        Route::get('/admin-subscription-dashboard', [AdminSubscriptionController::class, 'adminSubscriptionDashboard']);
        Route::post('/admin/subscriptions/change-subscription', [AdminSubscriptionController::class, 'changeSubscription']);
        Route::patch('/admin/subscriptions/change/{id}', [AdminSubscriptionController::class, 'changeStatus']);
    });
