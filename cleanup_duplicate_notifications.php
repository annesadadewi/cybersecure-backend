<?php

// Script cleanup: hapus duplikat notifikasi lama akibat timezone bug
// Jalankan sekali: php cleanup_duplicate_notifications.php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Cleanup Duplicate Notifications ===\n";

$totalBefore = DB::table('user_notifications')->count();
echo "Total before: {$totalBefore}\n";

// Temukan duplikat: notifikasi dengan user_id, category, marketplace_name, product_name, amount, dan occurred_at 
// dalam window 2 menit dari satu sama lain
// Strategi: simpan yang paling lama (id terkecil), hapus yang lebih baru (duplikat)

$users = DB::table('user_notifications')->distinct()->pluck('user_id');
$deleted = 0;

foreach ($users as $userId) {
    $notifications = DB::table('user_notifications')
        ->where('user_id', $userId)
        ->orderBy('occurred_at')
        ->orderBy('id')
        ->get(['id', 'category', 'marketplace_name', 'product_name', 'amount', 'occurred_at', 'is_read']);

    $toDelete = [];
    $seen = [];

    foreach ($notifications as $n) {
        // Buat key unik berdasarkan field-field utama
        $key = "{$n->category}|{$n->marketplace_name}|{$n->product_name}|{$n->amount}";
        
        if (isset($seen[$key])) {
            // Cek apakah occurred_at dalam window 2 menit
            $existingTime = strtotime($seen[$key]['occurred_at']);
            $currentTime  = strtotime($n->occurred_at);
            
            if (abs($currentTime - $existingTime) <= 120) {
                // Duplikat! Hapus yang ini (id lebih besar = lebih baru)
                // Tapi jika yang lama sudah is_read=true, pertahankan yang lama
                $toDelete[] = $n->id;
                continue;
            }
        }
        
        $seen[$key] = ['id' => $n->id, 'occurred_at' => $n->occurred_at];
    }

    if (!empty($toDelete)) {
        DB::table('user_notifications')->whereIn('id', $toDelete)->delete();
        $deleted += count($toDelete);
        echo "User {$userId}: deleted " . count($toDelete) . " duplicates\n";
    }
}

$totalAfter = DB::table('user_notifications')->count();
echo "\nDeleted: {$deleted} duplicate notifications\n";
echo "Total after: {$totalAfter}\n";
echo "Done!\n";
