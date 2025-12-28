<?php

use App\Http\Controllers\Frontend\Auth\SocialiteController;
use App\Http\Controllers\Frontend\UserProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/auth/{provider}/redirect', [SocialiteController::class, 'redirect']);
Route::get('/auth/{provider}/callback', [SocialiteController::class, 'callback']);



Route::middleware('auth:sanctum')->group(function () {
    Route::get('user-profile/show', [UserProfileController::class, 'showProfile']);
    Route::put('user-profile/update', [UserProfileController::class, 'update']);
});


