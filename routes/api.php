<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\MarketplaceController;
use App\Http\Controllers\API\ForgotPasswordController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public Authentication Routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Public Forgot Password (simulated OTP) Routes
Route::post('/forgot-password', [ForgotPasswordController::class, 'sendOtp']);
Route::post('/verify-otp', [ForgotPasswordController::class, 'verifyOtp']);
Route::post('/reset-password', [ForgotPasswordController::class, 'resetPassword']);

// Sanctum Protected Routes
Route::middleware('auth:sanctum')->group(function () {
    // Current user info and logout
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/logout', [AuthController::class, 'logout']);

    // Marketplace connection routes
    Route::get('/marketplaces', [MarketplaceController::class, 'index']);
    Route::post('/marketplaces', [MarketplaceController::class, 'store']);
    Route::delete('/marketplaces/{id}', [MarketplaceController::class, 'destroy']);
    Route::get('/marketplace/transactions', [MarketplaceController::class, 'getTransactions']);

    // Marketplace forgot/reset password routes
    Route::post('/marketplaces/forgot-password', [ForgotPasswordController::class, 'sendMarketplaceOtp']);
    Route::post('/marketplaces/reset-password', [ForgotPasswordController::class, 'resetMarketplacePassword']);
});