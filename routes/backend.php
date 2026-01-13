<?php

use App\Http\Controllers\Frontend\UserProfileController;
use App\Http\Controllers\SettingController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Backend\AdminPlanController;


    Route::controller(UserProfileController::class)->group(function () {
        Route::put('admin-profile/update', 'adminProfileUpdate')->middleware(['role:super_admin','auth:sanctum']);
    });

    Route::middleware('role:super_admin','auth:sanctum')->group(function () {
        Route::get('/settings', [SettingController::class, 'index']);
        Route::put('/settings/update', [SettingController::class, 'updateSettings']);
    });

    //Plan for admin.
    Route::middleware('auth:sanctum')->group(function () {
    Route::get('adminplan/', [AdminPlanController::class, 'index']);
    Route::post('plan/create', [AdminPlanController::class, 'store']);
    });
