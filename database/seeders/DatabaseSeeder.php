<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\UserActivitySeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@gmail.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
        ]);

        // Seed data aktivitas (marketplace, transaksi, notifikasi) untuk user baru
        (new UserActivitySeeder())->seedForUser($user);

        // Seed juga semua user lama yang belum punya data
        (new UserActivitySeeder())->seedForAllUsers();
    }
}
