<?php

use App\Http\Controllers\Frontend\Auth\RegisterController;
use App\Http\Controllers\Frontend\Auth\SocialiteController;
use App\Http\Controllers\Frontend\QrController;
use App\Http\Controllers\MessageTemplateController;
use App\Http\Controllers\Frontend\UserProfileController;
use Illuminate\Support\Facades\Route;



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


