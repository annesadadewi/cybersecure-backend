<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
   
    // Fungsi Register (Daftar)
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|min:3|max:75',
            'email' => 'required|string|email|min:8|max:65|ends_with:@gmail.com|unique:users',
            'password' => 'required|string|min:3|max:20|confirmed',
        ], [
            'name.min' => 'Nama minimal harus 3 karakter.',
            'name.max' => 'Nama maksimal 75 karakter.',
            'email.min' => 'Email minimal harus 8 karakter.',
            'email.max' => 'Email maksimal 65 karakter.',
            'email.ends_with' => 'Email harus menggunakan domain @gmail.com.',
            'password.min' => 'Kata sandi minimal harus 3 karakter.',
            'password.max' => 'Kata sandi maksimal 20 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.'
        ]);

        $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password, // <--- Hapus Hash::make-nya, biarkan polos
        ]);

        return response()->json([
            'message' => 'User berhasil didaftarkan',
            'user' => $user
        ], 201);
    }

    // Fungsi Login (Masuk)
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email|min:8|max:65|ends_with:@gmail.com',
            'password' => 'required|string|min:3|max:20',
        ], [
            'email.min' => 'Email minimal harus 8 karakter.',
            'email.max' => 'Email maksimal 65 karakter.',
            'email.ends_with' => 'Email harus menggunakan domain @gmail.com.',
            'password.min' => 'Kata sandi minimal harus 3 karakter.',
            'password.max' => 'Kata sandi maksimal 20 karakter.'
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        // Membuat Token (Sanctum)
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil',
            'token' => $token,
            'user' => $user
        ]);
    }

    // Fungsi Logout (Keluar)
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logout berhasil']);
    }
}
