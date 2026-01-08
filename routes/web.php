<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'page' => 'home'
    ]);
});


Route::get('/debug-subscription-class', function () {
    $subscription = auth()->user()->subscription('default');
    return [
        'class' => get_class($subscription),
        'methods' => get_class_methods($subscription),
    ];
});
