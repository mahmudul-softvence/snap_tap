<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


require __DIR__ . '/payment.php';
require __DIR__ . '/subscription.php';
require __DIR__ . '/frontend.php';


