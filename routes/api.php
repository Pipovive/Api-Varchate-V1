<?php

use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\AuthController;
use GuzzleHttp\Middleware;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Models\Usuario;
use Illuminate\Support\Facades\Mail;
use Illuminate\Foundation\Auth\EmailVerificationRequest;




Route::get('/test', [AuthController::class, 'test']);
Route::middleware('auth:sanctum')->get('/me', function (Request $request) {
    return $request->user();
});


Route::post('/register', [AuthController::class, 'register'])->name('/register');
Route::post('/login', [AuthController::class, 'login'])->name('/login');
Route::post('/auth/google', [AuthController::class, 'loginWithGoogle']);

Route::get('/test-email', function () {
    Mail::raw('Correo de prueba SMTP', function ($message) {
        $message->to('varchate25@gmail.com')
            ->subject('varchate sujeto');
    });
});

Route::middleware('auth:sanctum')->post('/email/resend', [AuthController::class, 'resendVerification']);


Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->name("verification.verify");

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('/logout');
    Route::post(
        '/email/verification-notification',
        [EmailVerificationController::class, 'resend']
    );
});
