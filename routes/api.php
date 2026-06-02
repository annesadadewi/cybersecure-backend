<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\MarketplaceController;
use App\Http\Controllers\API\TransactionLogController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\AnomalyController;
use App\Http\Controllers\API\ReportController;
use App\Http\Controllers\API\ForgotPasswordController;
use App\Http\Controllers\API\ProfileController;
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
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'profile_photo' => $user->profile_photo,
            'profile_photo_url' => $user->profile_photo_url,
        ]);
    });
    Route::post('/logout', [AuthController::class, 'logout']);

    // Profil & pengaturan akun CyberSecure
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/photo', [ProfileController::class, 'updatePhoto']);
    Route::delete('/profile/photo', [ProfileController::class, 'deletePhoto']);
    Route::put('/profile/password', [ProfileController::class, 'updatePassword']);

    // Marketplace connection routes
    Route::get('/marketplaces', [MarketplaceController::class, 'index']);
    Route::post('/marketplaces', [MarketplaceController::class, 'store']);
    Route::delete('/marketplaces/{id}', [MarketplaceController::class, 'destroy']);
    Route::get('/marketplace/transactions', [MarketplaceController::class, 'getTransactions']);

    // Transaction log (dashboard summary, filter, export)
    Route::get('/transactions/summary', [TransactionLogController::class, 'summary']);
    Route::get('/transactions/recent', [TransactionLogController::class, 'recent']);
    Route::get('/transactions/statistics', [TransactionLogController::class, 'statistics']);
    Route::get('/transactions/export', [TransactionLogController::class, 'export']);
    Route::get('/transactions', [TransactionLogController::class, 'index']);
    Route::get('/transactions/{id}', [TransactionLogController::class, 'show']);

    // Laporan historis per bulan
    Route::get('/reports/months-overview', [ReportController::class, 'monthsOverview']);
    Route::get('/reports/monthly', [ReportController::class, 'monthly']);
    Route::get('/reports/export', [ReportController::class, 'export']);

    // Anomali terdeteksi (keamanan + transaksi)
    Route::get('/anomalies/metrics', [AnomalyController::class, 'metrics']);
    Route::get('/anomalies', [AnomalyController::class, 'index']);
    Route::patch('/anomalies/{id}/status', [AnomalyController::class, 'updateStatus']);

    // Notifikasi (pemasukan, refund, dibatalkan — cancelled tidak masuk log transaksi)
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/mark-read', [NotificationController::class, 'bulkMarkRead']);
    Route::get('/notifications/transactions', [NotificationController::class, 'transactions']);

    // Marketplace forgot/reset password routes
    Route::post('/marketplaces/forgot-password', [ForgotPasswordController::class, 'sendMarketplaceOtp']);
    Route::post('/marketplaces/reset-password', [ForgotPasswordController::class, 'resetMarketplacePassword']);
});