<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::get('verify', [AuthController::class, 'verify'])->middleware('firebase.auth');
