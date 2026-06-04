<?php

namespace App\Services;

use App\Models\MockTransaction;
use App\Models\User;
use App\Models\UserMarketplace;
use App\Models\UserNotification;
use Carbon\Carbon;

/**
 * Service untuk generate data aktivitas lengkap (marketplace, transaksi, notifikasi)
 * untuk satu user. Dipanggil saat registrasi dan bisa juga dipanggil manual
 * untuk akun-akun lama yang belum punya data.
 */
class UserActivitySeeder
{
    private const REPORT_YEAR = 2026;
    private const JUNE_PARTIAL_END_DAY = 7;
    private const SEED_MONTHS = [1, 2, 3, 4, 6];
    private const CONNECTED_MARKETPLACES = ['Shopee', 'Tokopedia', 'Blibli', 'Lazada'];

    private const MONTH_VOLUME = [
        1 => 1.00,
        2 => 0.88,
        3 => 1.12,
        4 => 0.95,
        6 => 1.00,
    ];

    private array $products = [
        'Shopee' => [
            ['name' => 'Sepatu Sneakers Pria', 'amount' => 350000],
            ['name' => 'Jaket Hoodie Waterproof', 'amount' => 220000],
            ['name' => 'Kaos Cotton Combed 30s', 'amount' => 75000],
            ['name' => 'Tas Ransel Laptop', 'amount' => 180000],
            ['name' => 'Kacamata Hitam Polarized', 'amount' => 120000],
        ],
        'Tokopedia' => [
            ['name' => 'Mechanical Keyboard RGB', 'amount' => 850000],
            ['name' => 'Wireless Gaming Mouse', 'amount' => 450000],
            ['name' => 'Monitor Gaming 24 Inch', 'amount' => 1950000],
            ['name' => 'Headphone Bluetooth Bass', 'amount' => 380000],
            ['name' => 'Mousepad Deskmat Extra Large', 'amount' => 150000],
        ],
        'Blibli' => [
            ['name' => 'Smartwatch Fitness Tracker', 'amount' => 600000],
            ['name' => 'TWS Earbuds ANC', 'amount' => 520000],
            ['name' => 'Powerbank 20000mAh', 'amount' => 280000],
            ['name' => 'Charger GaN 65W Fast Charge', 'amount' => 320000],
        ],
        'Lazada' => [
            ['name' => 'Kipas Angin Portable Mini', 'amount' => 85000],
            ['name' => 'Casing HP Matte Premium', 'amount' => 45000],
        ],
    ];

    /**
     * Seed semua data aktivitas untuk satu user.
     * Idempotent — tidak akan duplikat jika dipanggil ulang.
     */
    public function seedForUser(User $user): void
    {
        $this->ensureMarketplaces($user->id);
        $this->seedTransactions($user->id);
        $this->seedNotifications($user->id);
    }

    /**
     * Seed data aktivitas untuk SEMUA user yang belum punya transaksi.
     */
    public function seedForAllUsers(): int
    {
        $users = User::all();
        $seeded = 0;

        foreach ($users as $user) {
            $hasTransactions = MockTransaction::where('user_id', $user->id)->exists();
            if (!$hasTransactions) {
                $this->seedForUser($user);
                $seeded++;
            }
        }

        return $seeded;
    }

    private function ensureMarketplaces(int $userId): void
    {
        $defaults = [
            ['marketplace_name' => 'Tokopedia', 'marketplace_email' => 'tokopedia@test.local', 'password' => 'token_tokopedia', 'status' => 'connected'],
            ['marketplace_name' => 'Shopee', 'marketplace_email' => 'shopee@test.local', 'password' => 'token_shopee', 'status' => 'connected'],
            ['marketplace_name' => 'Blibli', 'marketplace_email' => 'blibli@test.local', 'password' => 'token_blibli', 'status' => 'connected'],
            ['marketplace_name' => 'Lazada', 'marketplace_email' => 'lazada@test.local', 'password' => 'token_lazada', 'status' => 'connected'],
        ];

        foreach ($defaults as $mp) {
            UserMarketplace::updateOrCreate(
                ['user_id' => $userId, 'marketplace_name' => $mp['marketplace_name']],
                [
                    'marketplace_email' => $mp['marketplace_email'],
                    'password' => $mp['password'],
                    'status' => 'connected',
                ]
            );
        }
    }

    private function seedTransactions(int $userId): void
    {
        $tz = 'Asia/Jakarta';

        foreach (self::SEED_MONTHS as $month) {
            [$from, $to] = $this->monthBounds($month, $tz);

            // Hapus data lama di bulan ini (kecuali Mei)
            MockTransaction::where('user_id', $userId)
                ->whereBetween('transaction_date', [$from, $to])
                ->delete();

            $this->seedMonth($userId, $from, $to, $month);
        }
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function monthBounds(int $month, string $tz): array
    {
        $from = Carbon::create(self::REPORT_YEAR, $month, 1, 0, 0, 0, $tz)->startOfDay();
        $lastDay = ($month === 6)
            ? self::JUNE_PARTIAL_END_DAY
            : $from->copy()->endOfMonth()->day;
        $to = Carbon::create(self::REPORT_YEAR, $month, $lastDay, 23, 59, 59, $tz);

        return [$from, $to];
    }

    private function seedMonth(int $userId, Carbon $from, Carbon $to, int $month): int
    {
        $batch = [];
        $inserted = 0;
        $cursor = $from->copy()->startOfDay();
        $monthFactor = self::MONTH_VOLUME[$month] ?? 1.0;

        $marketplaces = UserMarketplace::where('user_id', $userId)
            ->where('status', 'connected')
            ->pluck('marketplace_name')
            ->filter(fn ($name) => isset($this->products[$name]))
            ->values()
            ->all();

        if ($marketplaces === []) {
            $marketplaces = self::CONNECTED_MARKETPLACES;
        }

        while ($cursor->lte($to)) {
            $isWeekend = in_array($cursor->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY], true);
            $basePerDay = $isWeekend ? rand(4, 7) : rand(5, 10);
            $txPerDay = max(3, (int) round($basePerDay * $monthFactor));

            for ($i = 0; $i < $txPerDay; $i++) {
                $mpName = $marketplaces[array_rand($marketplaces)];
                $prod = $this->products[$mpName][array_rand($this->products[$mpName])];

                $variation = rand(-22, 25) / 100;
                $amount = max((int) round($prod['amount'] * (1 + $variation)), 25000);

                [$type, $status] = $this->resolveTypeAndStatus($cursor);

                $batch[] = [
                    'user_id' => $userId,
                    'marketplace_name' => $mpName,
                    'product_name' => $prod['name'],
                    'amount' => $amount,
                    'type' => $type,
                    'status' => $status,
                    'transaction_date' => $cursor->copy()->setTime(rand(8, 22), rand(0, 59)),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (count($batch) >= 400) {
                    MockTransaction::insert($batch);
                    $inserted += count($batch);
                    $batch = [];
                }
            }

            $cursor->addDay();
        }

        if (!empty($batch)) {
            MockTransaction::insert($batch);
            $inserted += count($batch);
        }

        return $inserted;
    }

    /** @return array{0: string, 1: string} */
    private function resolveTypeAndStatus(Carbon $date): array
    {
        $roll = rand(1, 100);

        if ($roll <= 13) {
            return [MockTransaction::TYPE_REFUND, MockTransaction::STATUS_REFUND];
        }

        if ($roll <= 20) {
            return [MockTransaction::TYPE_INCOME, MockTransaction::STATUS_SUSPICIOUS];
        }

        if ($roll <= 26) {
            return [MockTransaction::TYPE_INCOME, MockTransaction::STATUS_FAILED];
        }

        if ($roll <= 30 && $date->day % 7 === 0) {
            return [MockTransaction::TYPE_INCOME, MockTransaction::STATUS_PENDING];
        }

        return [MockTransaction::TYPE_INCOME, MockTransaction::STATUS_SUCCESS];
    }

    private function seedNotifications(int $userId): void
    {
        // Hapus notifikasi lama supaya ter-generate ulang dengan teks terbaru
        UserNotification::where('user_id', $userId)->delete();

        $now = Carbon::now('Asia/Jakarta');
        $samples = [
            [
                'category' => 'cancelled',
                'title' => 'Transaksi Dibatalkan',
                'message' => 'Customer membatalkan pesan: Kipas Angin Portable Mini (Lazada)',
                'marketplace_name' => 'Lazada',
                'product_name' => 'Kipas Angin Portable Mini',
                'amount' => 85850,
                'days_ago' => 0,
            ],
            [
                'category' => 'return_request',
                'title' => 'Permintaan Retur Baru',
                'message' => 'Permintaan retur baru: Mechanical Keyboard RGB (Tokopedia)',
                'marketplace_name' => 'Tokopedia',
                'product_name' => 'Mechanical Keyboard RGB',
                'amount' => 850000,
                'days_ago' => 1,
            ],
            [
                'category' => 'sync',
                'title' => 'Proses Sinkronisasi Toko',
                'message' => 'Sinkronisasi stok Shopee sedang berjalan — mohon tunggu',
                'marketplace_name' => 'Shopee',
                'product_name' => null,
                'amount' => null,
                'days_ago' => 1,
            ],
            [
                'category' => 'cancelled',
                'title' => 'Transaksi Dibatalkan',
                'message' => 'Customer membatalkan pesan: Casing HP Matte Premium (Lazada)',
                'marketplace_name' => 'Lazada',
                'product_name' => 'Casing HP Matte Premium',
                'amount' => 45000,
                'days_ago' => 2,
            ],
        ];

        foreach ($samples as $s) {
            UserNotification::create([
                'user_id' => $userId,
                'category' => $s['category'],
                'title' => $s['title'],
                'message' => $s['message'],
                'marketplace_name' => $s['marketplace_name'],
                'product_name' => $s['product_name'],
                'amount' => $s['amount'],
                'is_read' => false,
                'occurred_at' => (clone $now)->subDays($s['days_ago'])->subHours(rand(1, 8)),
            ]);
        }
    }
}
