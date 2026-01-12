<?php

use App\Http\Controllers\Frontend\UserProfileController;
use App\Http\Controllers\SettingController;
use Illuminate\Support\Facades\Route;


    Route::controller(UserProfileController::class)->group(function () {
        Route::put('admin-profile/update', 'adminProfileUpdate')->middleware(['role:super_admin','auth:sanctum']);
    });

    Route::middleware('role:super_admin','auth:sanctum')->group(function () {
        Route::get('/settings', [SettingController::class, 'index']);
        Route::put('/settings/update', [SettingController::class, 'updateSettings']);
    });


