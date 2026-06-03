<?php
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->handle(Request::capture());

try {
    $seeder = new \App\Services\UserActivitySeeder();
    $users = \App\Models\User::all();
    $count = 0;
    foreach ($users as $user) {
        $seeder->seedForUser($user);
        $count++;
    }
    echo "<h1>Sukses!</h1><p>Berhasil melakukan force-seed data aktivitas (Transaksi + Notifikasi) untuk $count user.</p>";
} catch (\Exception $e) {
    echo "<h1>Error!</h1><pre>" . $e->getMessage() . "\n" . $e->getTraceAsString() . "</pre>";
}
