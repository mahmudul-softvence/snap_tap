<?php

use App\Http\Controllers\Frontend\Auth\RegisterController;
use App\Http\Controllers\Frontend\Auth\SocialiteController;
use App\Http\Controllers\Frontend\FacebookController;
use App\Http\Controllers\Frontend\GmbController;
use App\Http\Controllers\Frontend\GmbMockVersionController;
use App\Http\Controllers\Frontend\BasicSettingController;
use App\Http\Controllers\Frontend\DashboardController;
use App\Http\Controllers\Frontend\MessageTemplateController;
use App\Http\Controllers\Frontend\QrController;
use App\Http\Controllers\Frontend\ReviewReqController;
use App\Http\Controllers\Frontend\UserProfileController;
use App\Http\Controllers\Frontend\GoogleBusinessController;
use App\Http\Controllers\Frontend\ReviewController;
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

    Route::get('change_review_status/{id}', [ReviewReqController::class, 'change_review_status']);
});


Route::middleware('auth:sanctum')->group(function () {

    // Dashboard/Analytics
    Route::controller(DashboardController::class)->group(function () {
        Route::get('dashboard',  'dashboard');
        Route::get('analytics', 'analytics');
    });


    // Logout/Change pwd
    Route::controller(RegisterController::class)->group(function () {
        Route::post('logout',  'logout');
        Route::post('change_pwd', 'change_pwd');
    });

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
        Route::get('message_template/default_template', 'default_template');
    });

    // User Profile
    Route::controller(UserProfileController::class)->group(function () {
        Route::get('user-profile/show', 'showProfile');
        Route::put('user-profile/update', 'update');
    });

    // Reviews
    Route::controller(ReviewReqController::class)->group(function () {
        Route::get('review_req', 'index');
        Route::post('review_req/create', 'create');
        Route::put('review_req/update/{id}', 'update');
        Route::get('review_req/show/{id}', 'show');
        Route::delete('review_req/delete/{id}', 'destory');
    });

    // Basic Settings
    Route::controller(BasicSettingController::class)->group(function () {
        Route::get('settings', 'index');
        Route::put('settings/update', 'update');
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
    Route::get('/facebook/pages', [FacebookController::class, 'pages']);
    Route::get('/facebook/reviews/{page}', [FacebookController::class, 'reviews']);
    Route::post('/facebook/reply', [FacebookController::class, 'reply']);
});


Route::middleware('auth:sanctum')->group(function () {

    // Review List + Filters
    Route::get('/reviews', [ReviewController::class, 'index']);

    // Reply to Review
    Route::post('/reviews/reply', [ReviewController::class, 'reply']);
    Route::delete('/reviews/reply', [ReviewController::class, 'deleteReply']);

});

