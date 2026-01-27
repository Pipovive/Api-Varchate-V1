<?php

use App\Http\Controllers\AuthController;
use GuzzleHttp\Middleware;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;


Route::get('/test', [AuthController::class, 'test']);
Route::middleware('auth:sanctum')->get('/me', function (Request $request) {
    return $request->user();
});


Route::post('/register',[AuthController::class, 'register'])->name('/register');
Route::post('/login', [AuthController::class, 'login'])->name('/login');
Route::post('/auth/google', [AuthController::class, 'loginWithGoogle'])->name('/auth/google');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('/logout');
});


