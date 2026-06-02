<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    private function profilePayload(User $user): array
    {
        $photoUrl = null;
        if ($user->profile_photo) {
            $photoUrl = Storage::disk('public')->url($user->profile_photo);
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'profile_photo' => $user->profile_photo,
            'profile_photo_url' => $photoUrl,
        ];
    }

    public function show(Request $request)
    {
        return response()->json([
            'profile' => $this->profilePayload($request->user()),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|min:3|max:65',
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'min:8',
                'max:65',
                'ends_with:@gmail.com',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'phone' => 'nullable|string|min:10|max:15|regex:/^[0-9+\-\s]+$/',
        ], [
            'name.min' => 'Nama minimal harus 3 karakter.',
            'name.max' => 'Nama maksimal 65 karakter.',
            'email.min' => 'Email minimal harus 8 karakter.',
            'email.max' => 'Email maksimal 65 karakter.',
            'email.ends_with' => 'Email harus menggunakan domain @gmail.com.',
            'email.unique' => 'Email sudah digunakan akun lain.',
            'phone.min' => 'Nomor telepon minimal 10 digit.',
            'phone.max' => 'Nomor telepon maksimal 15 karakter.',
            'phone.regex' => 'Nomor telepon hanya boleh berisi angka, spasi, +, atau -.',
        ]);

        $user->fill($validated);
        $user->save();

        return response()->json([
            'message' => 'Profil berhasil diperbarui',
            'profile' => $this->profilePayload($user->fresh()),
        ]);
    }

    public function updatePhoto(Request $request)
    {
        $request->validate([
            'photo' => 'required|image|mimes:jpeg,jpg,png,webp|max:2048',
        ], [
            'photo.required' => 'Foto profil wajib diunggah.',
            'photo.image' => 'File harus berupa gambar.',
            'photo.max' => 'Ukuran foto maksimal 2 MB.',
        ]);

        $user = $request->user();

        if ($user->profile_photo) {
            Storage::disk('public')->delete($user->profile_photo);
        }

        $path = $request->file('photo')->store('profile-photos/' . $user->id, 'public');
        $user->update(['profile_photo' => $path]);

        return response()->json([
            'message' => 'Foto profil berhasil diperbarui',
            'profile' => $this->profilePayload($user->fresh()),
        ]);
    }

    public function deletePhoto(Request $request)
    {
        $user = $request->user();

        if ($user->profile_photo) {
            Storage::disk('public')->delete($user->profile_photo);
            $user->update(['profile_photo' => null]);
        }

        return response()->json([
            'message' => 'Foto profil berhasil dihapus',
            'profile' => $this->profilePayload($user),
        ]);
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|max:20|confirmed',
        ], [
            'password.min' => 'Kata sandi minimal harus 8 karakter.',
            'password.max' => 'Kata sandi maksimal 20 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Kata sandi saat ini tidak sesuai.'],
            ]);
        }

        $user->update(['password' => $request->password]);

        return response()->json([
            'message' => 'Kata sandi Akun Utama CyberSecure berhasil diubah.',
        ]);
    }
}
