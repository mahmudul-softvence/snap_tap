<?php

use App\Http\Controllers\Frontend\Auth\RegisterController;
use App\Http\Controllers\Frontend\Auth\SocialiteController;
use App\Http\Controllers\Frontend\FacebookController;
use App\Http\Controllers\Frontend\GmbController;
use App\Http\Controllers\Frontend\GmbMockVersionController;
use App\Http\Controllers\Frontend\QrController;
use App\Http\Controllers\MessageTemplateController;
use App\Http\Controllers\Frontend\UserProfileController;
use App\Http\Controllers\Frontend\GoogleBusinessController;
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

    // User Profile
    Route::controller(UserProfileController::class)->group(function () {
        Route::get('user-profile/show', 'showProfile');
        Route::put('user-profile/update', 'update');
    });
});

// -----------For Production Google Business Profile (GMB) connector route------------


Route::get('/google/gmb/callback', [GmbController::class, 'callback']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/google/gmb/auth-url', [GmbController::class, 'authUrl']);
    Route::get('/gmb/accounts', [GmbController::class, 'accounts']);
    Route::get('/gmb/locations/{account}', [GmbController::class, 'locations']);
    Route::get('/gmb/reviews/{location}', [GmbController::class, 'reviews']);
    Route::post('/gmb/reply', [GmbController::class, 'reply']);
});
// -------------------------------------------------------------------------------------

/// fake GMB routes for testing (Mock Version)
// Route::get('/google/gmb/auth-url', [GmbMockVersionController::class, 'authUrl']);
// Route::get('/google/gmb/callback', [GmbMockVersionController::class, 'callback']);

// Route::middleware('auth:sanctum')->group(function () {
//     Route::get('/gmb/accounts', [GmbMockVersionController::class, 'accounts']);
//     Route::get('/gmb/locations/{account}', [GmbMockVersionController::class, 'locations'])->where('account', '.*');
//     Route::get('/gmb/reviews/{location}', [GmbMockVersionController::class, 'reviews'])->where('location', '.*');
//     Route::post('/gmb/reply', [GmbMockVersionController::class, 'reply']);
// });





Route::get('/facebook/callback', [FacebookController::class, 'callback']);
// Facebook
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/facebook/auth-url', [FacebookController::class, 'authUrl']);
    Route::post('/facebook/connect-page', [FacebookController::class, 'connectPage']);
    // Route::get('/facebook/accounts/meta', [FacebookController::class, 'metaData']);
    Route::get('/facebook/pages', [FacebookController::class, 'pages']);
    Route::get('/facebook/reviews/{page}', [FacebookController::class, 'reviews']);
    Route::post('/facebook/reply', [FacebookController::class, 'reply']);
});


// --------For Production Facebook page connector route---------------------------------
// Route::middleware('auth:sanctum')->group(function () {
//     Route::get('/facebook/auth-url', [FacebookController::class, 'authUrl']);
//     Route::get('/facebook/callback', [FacebookController::class, 'callback']);
//     Route::post('/facebook/connect-page', [FacebookController::class, 'connectPage']);
//     Route::get('/facebook/pages', [FacebookController::class, 'pages']);
//     Route::get('/facebook/reviews/{page}', [FacebookController::class, 'reviews']);
//     Route::post('/facebook/reply', [FacebookController::class, 'reply']);
// });
// -------------------------------------------------------------------------------------
