<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\MockTransaction;
use App\Models\UserMarketplace;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Simulasi transaksi masuk + refund Jan–Jun 2026 (Juni s/d tgl 7).
 * MEI tidak disentuh per user. Berlaku untuk SEMUA akun di database.
 */
class MockTransactionSeeder extends Seeder
{
    private const REPORT_YEAR = 2026;

    private const JUNE_PARTIAL_END_DAY = 7;

    private const SEED_MONTHS = [1, 2, 3, 4, 6];

    private const CONNECTED_MARKETPLACES = ['Shopee', 'Tokopedia', 'Blibli'];

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

    public function run(): void
    {
        $users = User::all();
        if ($users->isEmpty()) {
            $this->command?->warn('Tidak ada user.');

            return;
        }

        $grandTotal = 0;

        foreach ($users as $user) {
            $this->command?->info("── User #{$user->id} ({$user->email}) ──");
            $this->ensureMarketplaces($user->id);

            $tz = 'Asia/Jakarta';
            $userTotal = 0;

            [$mayFrom, $mayTo] = $this->monthBounds(5, $tz);
            $mayCount = MockTransaction::where('user_id', $user->id)
                ->whereBetween('transaction_date', [$mayFrom, $mayTo])
                ->count();
            $this->command?->info("  Mei " . self::REPORT_YEAR . " — tidak disentuh ({$mayCount} transaksi).");

            foreach (self::SEED_MONTHS as $month) {
                [$from, $to] = $this->monthBounds($month, $tz);

                $removed = MockTransaction::where('user_id', $user->id)
                    ->whereBetween('transaction_date', [$from, $to])
                    ->delete();

                $count = $this->seedMonth($user->id, $from, $to, $month);
                $userTotal += $count;

                $this->command?->info(
                    '  ' . $this->monthLabel($month) . ": +{$count}" . ($removed > 0 ? " (ganti {$removed} di bulan ini)" : '')
                );
            }

            $grandTotal += $userTotal;
            $this->command?->info("  Subtotal user: {$userTotal} transaksi baru.");
        }

        $this->command?->info("Selesai. Total semua user: {$grandTotal} transaksi.");
    }

    private function monthLabel(int $month): string
    {
        return match ($month) {
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            6 => 'Juni (s/d tgl ' . self::JUNE_PARTIAL_END_DAY . ')',
            default => "Bulan {$month}",
        };
    }

    private function ensureMarketplaces(int $userId): void
    {
        $defaults = [
            ['marketplace_name' => 'Tokopedia', 'marketplace_email' => 'tokopedia@test.local', 'password' => 'token_tokopedia', 'status' => 'connected'],
            ['marketplace_name' => 'Shopee', 'marketplace_email' => 'shopee@test.local', 'password' => 'token_shopee', 'status' => 'connected'],
            ['marketplace_name' => 'Blibli', 'marketplace_email' => 'blibli@test.local', 'password' => 'token_blibli', 'status' => 'connected'],
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
}
