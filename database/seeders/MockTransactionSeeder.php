<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\MockTransaction;
use App\Models\UserMarketplace;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class MockTransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::first();
        if (!$user) {
            return;
        }

        // 1. Seed connected marketplaces for the test user to make the UI look rich
        $marketplaces = [
            [
                'marketplace_name' => 'Tokopedia',
                'marketplace_email' => 'tokopedia.test@gmail.com',
                'password' => 'token_tokopedia_123',
                'status' => 'connected'
            ],
            [
                'marketplace_name' => 'Shopee',
                'marketplace_email' => 'shopee.test@gmail.com',
                'password' => 'token_shopee_456',
                'status' => 'connected'
            ],
            [
                'marketplace_name' => 'Blibli',
                'marketplace_email' => 'blibli.test@gmail.com',
                'password' => 'token_blibli_789',
                'status' => 'connected'
            ],
            [
                'marketplace_name' => 'Lazada',
                'marketplace_email' => 'lazada.test@gmail.com',
                'password' => 'token_lazada_abc',
                'status' => 'disconnected'
            ],
            [
                'marketplace_name' => 'Bukalapak',
                'marketplace_email' => 'bukalapak.test@gmail.com',
                'password' => 'token_bukalapak_xyz',
                'status' => 'disconnected'
            ]
        ];

        foreach ($marketplaces as $mp) {
            UserMarketplace::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'marketplace_name' => $mp['marketplace_name'],
                ],
                [
                    'marketplace_email' => $mp['marketplace_email'],
                    'password' => $mp['password'],
                    'status' => $mp['status'],
                ]
            );
        }

        // 2. Seed mock transactions for each of the marketplaces over the last 10 days
        $products = [
            'Shopee' => [
                ['name' => 'Sepatu Sneakers Pria', 'amount' => 350000],
                ['name' => 'Jaket Hoodie Waterproof', 'amount' => 220000],
                ['name' => 'Kaos Cotton Combed 30s', 'amount' => 75000],
                ['name' => 'Tas Ransel Laptop', 'amount' => 180000],
                ['name' => 'Kacamata Hitam Polarized', 'amount' => 120000]
            ],
            'Tokopedia' => [
                ['name' => 'Mechanical Keyboard RGB', 'amount' => 850000],
                ['name' => 'Wireless Gaming Mouse', 'amount' => 450000],
                ['name' => 'Monitor Gaming 24 Inch', 'amount' => 1950000],
                ['name' => 'Headphone Bluetooth Bass', 'amount' => 380000],
                ['name' => 'Mousepad Deskmat Extra Large', 'amount' => 150000]
            ],
            'Blibli' => [
                ['name' => 'Smartwatch Fitness Tracker', 'amount' => 600000],
                ['name' => 'TWS Earbuds ANC', 'amount' => 520000],
                ['name' => 'Powerbank 20000mAh', 'amount' => 280000],
                ['name' => 'Charger GaN 65W Fast Charge', 'amount' => 320000]
            ],
            'Lazada' => [
                ['name' => 'Kipas Angin Portable Mini', 'amount' => 85000],
                ['name' => 'Casing HP Matte Premium', 'amount' => 45000]
            ],
            'Bukalapak' => [
                ['name' => 'SSD M.2 NVMe 1TB', 'amount' => 1100000],
                ['name' => 'RAM DDR4 16GB Dual Channel', 'amount' => 750000]
            ]
        ];

        // Seed transactions for the last 10 days
        $now = Carbon::now('Asia/Jakarta');
        
        // Generate transactions
        foreach (['Shopee', 'Tokopedia', 'Blibli', 'Lazada', 'Bukalapak'] as $mpName) {
            $mpProducts = $products[$mpName];
            
            for ($i = 0; $i < 15; $i++) {
                // Pick random product
                $prod = $mpProducts[array_rand($mpProducts)];
                
                // Random date in the last 10 days
                $daysAgo = rand(0, 10);
                $randomHour = rand(8, 22);
                $randomMinute = rand(0, 59);
                $date = (clone $now)->subDays($daysAgo)->setHour($randomHour)->setMinute($randomMinute);
                
                // Varied amount around the base product amount (+- 20%)
                $variation = rand(-20, 20) / 100;
                $finalAmount = (int) ($prod['amount'] * (1 + $variation));
                
                MockTransaction::create([
                    'user_id' => $user->id,
                    'marketplace_name' => $mpName,
                    'product_name' => $prod['name'],
                    'amount' => $finalAmount,
                    'transaction_date' => $date,
                ]);
            }
        }
    }
}
