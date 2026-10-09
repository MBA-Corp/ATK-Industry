<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1');

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth');
