<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\UserActivitySeeder;
use Illuminate\Console\Command;

/**
 * Artisan command untuk seed data aktivitas ke semua akun yang belum punya,
 * atau ke satu akun tertentu via --email option.
 *
 * Usage:
 *   php artisan users:seed-activity              # semua user yang belum punya data
 *   php artisan users:seed-activity --all         # paksa re-seed semua user
 *   php artisan users:seed-activity --email=kayla23@gmail.com  # satu user spesifik
 */
class SeedUserActivity extends Command
{
    protected $signature = 'users:seed-activity
                            {--email= : Seed untuk satu user berdasarkan email}
                            {--all : Paksa re-seed semua user (termasuk yang sudah punya data)}';

    protected $description = 'Seed data aktivitas (marketplace, transaksi, notifikasi) untuk user';

    public function handle(): int
    {
        $seeder = new UserActivitySeeder();

        if ($email = $this->option('email')) {
            $user = User::where('email', $email)->first();
            if (!$user) {
                $this->error("User dengan email '{$email}' tidak ditemukan.");
                return self::FAILURE;
            }

            $this->info("Seeding data untuk: {$user->name} ({$user->email})...");
            $seeder->seedForUser($user);
            $this->info('Selesai!');
            return self::SUCCESS;
        }

        if ($this->option('all')) {
            $users = User::all();
            $bar = $this->output->createProgressBar($users->count());
            $bar->start();

            foreach ($users as $user) {
                $seeder->seedForUser($user);
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info("Selesai! {$users->count()} user di-seed.");
            return self::SUCCESS;
        }

        // Default: hanya user yang belum punya transaksi
        $count = $seeder->seedForAllUsers();
        $this->info("Selesai! {$count} user baru di-seed.");
        return self::SUCCESS;
    }
}
