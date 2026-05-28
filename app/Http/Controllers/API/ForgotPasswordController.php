<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserMarketplace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class ForgotPasswordController extends Controller
{
    /**
     * Send OTP for CyberSecure Main Account
     */
    public function sendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|ends_with:@gmail.com',
        ], [
            'email.ends_with' => 'Email harus menggunakan domain @gmail.com.',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'Email tidak terdaftar pada sistem CyberSecure.'
            ], 404);
        }

        // Generate 4-digit random OTP
        $otp = (string) rand(1000, 9999);

        // Store OTP in Cache for 10 minutes
        Cache::put('otp_main_' . $request->email, $otp, now()->addMinutes(10));

        return response()->json([
            'message' => 'Kode OTP Reset Password berhasil dibuat (Simulasi).',
            'email' => $request->email,
            'otp' => $otp, // Return OTP in response for simulation popup
        ], 200);
    }

    /**
     * Verify OTP for CyberSecure Main Account
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string|size:4',
        ]);

        $cachedOtp = Cache::get('otp_main_' . $request->email);

        if (!$cachedOtp || $cachedOtp !== $request->otp) {
            return response()->json([
                'message' => 'Kode OTP tidak valid atau sudah kedaluwarsa.'
            ], 422);
        }

        return response()->json([
            'message' => 'Kode OTP terverifikasi. Silakan masukkan password baru.',
            'status' => 'verified'
        ], 200);
    }

    /**
     * Reset Password for CyberSecure Main Account
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string|size:4',
            'password' => 'required|string|min:3|max:20|confirmed',
        ], [
            'password.min' => 'Kata sandi minimal harus 3 karakter.',
            'password.max' => 'Kata sandi maksimal 20 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.'
        ]);

        $cachedOtp = Cache::get('otp_main_' . $request->email);

        if (!$cachedOtp || $cachedOtp !== $request->otp) {
            return response()->json([
                'message' => 'Sesi reset password tidak valid atau sudah kedaluwarsa.'
            ], 422);
        }

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan.'], 404);
        }

        // Update password (will be hashed automatically via casts, but Hash::make is safe)
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Clear OTP from Cache
        Cache::forget('otp_main_' . $request->email);

        return response()->json([
            'message' => 'Kata sandi Akun Utama CyberSecure berhasil diubah. Silakan masuk kembali.'
        ], 200);
    }

    /**
     * Send OTP for Connected Marketplace Account (Protected route)
     */
    public function sendMarketplaceOtp(Request $request)
    {
        $request->validate([
            'marketplace_name' => 'required|string',
            'marketplace_email' => 'required|email',
        ]);

        $user = $request->user();
        
        // Find if user actually has this marketplace connected
        $mp = UserMarketplace::where('user_id', $user->id)
            ->where('marketplace_name', $request->marketplace_name)
            ->where('marketplace_email', $request->marketplace_email)
            ->first();

        if (!$mp) {
            return response()->json([
                'message' => 'Akun marketplace dengan email tersebut tidak ditemukan atau belum terintegrasi.'
            ], 404);
        }

        // Generate 4-digit random OTP
        $otp = (string) rand(1000, 9999);

        // Store OTP in Cache
        $cacheKey = 'otp_mp_' . $user->id . '_' . $request->marketplace_name;
        Cache::put($cacheKey, $otp, now()->addMinutes(10));

        return response()->json([
            'message' => 'Kode OTP Reset Toko berhasil dibuat (Simulasi).',
            'marketplace_name' => $request->marketplace_name,
            'marketplace_email' => $request->marketplace_email,
            'otp' => $otp, // Return OTP in response for simulation popup
        ], 200);
    }

    /**
     * Reset Password for Connected Marketplace Account (Protected route)
     */
    public function resetMarketplacePassword(Request $request)
    {
        $request->validate([
            'marketplace_name' => 'required|string',
            'marketplace_email' => 'required|email',
            'otp' => 'required|string|size:4',
            'password' => 'required|string|min:4', // New password/token
        ]);

        $user = $request->user();
        
        // Verify OTP
        $cacheKey = 'otp_mp_' . $user->id . '_' . $request->marketplace_name;
        $cachedOtp = Cache::get($cacheKey);

        if (!$cachedOtp || $cachedOtp !== $request->otp) {
            return response()->json([
                'message' => 'Kode OTP tidak valid atau sudah kedaluwarsa.'
            ], 422);
        }

        $mp = UserMarketplace::where('user_id', $user->id)
            ->where('marketplace_name', $request->marketplace_name)
            ->where('marketplace_email', $request->marketplace_email)
            ->first();

        if (!$mp) {
            return response()->json([
                'message' => 'Akun marketplace tidak ditemukan.'
            ], 404);
        }

        // Update password/token for marketplace
        $mp->update([
            'password' => $request->password,
            'status' => 'connected' // Resetting password re-establishes connection status if it was disconnected
        ]);

        // Clear OTP from Cache
        Cache::forget($cacheKey);

        return response()->json([
            'message' => 'Kata sandi / token Toko ' . $request->marketplace_name . ' berhasil diperbarui.'
        ], 200);
    }
}
