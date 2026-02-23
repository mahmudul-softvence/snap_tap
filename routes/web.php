<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('frontend.index');
});

Route::get('/a', function () {
    return view('frontend.email_verified');
})->name('about');


Route::get('/debug-subscription-class', function () {
    $subscription = auth()->user()->subscription('default');
    return [
        'class' => get_class($subscription),
        'methods' => get_class_methods($subscription),
    ];
});


Route::get('/terms-conditions', function () {
    return view('frontend.terms-conditions');
})->name('terms.conditions');

Route::get('/privacy-policy', function () {
    return view('frontend.privacy-policy');
})->name('privacy.policy');




