<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserNotification;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class UserNotificationSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();
        if (!$user) {
            return;
        }

        UserNotification::where('user_id', $user->id)->delete();

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
                'user_id' => $user->id,
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
