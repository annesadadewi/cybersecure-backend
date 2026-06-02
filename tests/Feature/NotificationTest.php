<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\MockTransaction;
use App\Models\UserMarketplace;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Illuminate\Support\Carbon;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        // Hubungkan marketplace Tokopedia untuk user
        UserMarketplace::create([
            'user_id' => $this->user->id,
            'marketplace_name' => 'Tokopedia',
            'marketplace_email' => 'seller@tokopedia.local',
            'password' => 'secret_token',
            'status' => 'connected',
        ]);
    }

    /**
     * Test first sync creates notifications, second sync does not create duplicates.
     */
    public function test_sync_does_not_create_duplicate_notifications(): void
    {
        Sanctum::actingAs($this->user);

        // Buat mock transaksi income
        $tx = MockTransaction::create([
            'user_id' => $this->user->id,
            'marketplace_name' => 'Tokopedia',
            'product_name' => 'Gundam RG 1/144',
            'amount' => 500000,
            'type' => 'income',
            'status' => 'success',
            'transaction_date' => Carbon::now('Asia/Jakarta')->subMinutes(30),
        ]);

        // Hit index pertama kali -> sync dipicu
        $response1 = $this->getJson('/api/notifications');
        $response1->assertStatus(200);

        // Pastikan notifikasi berhasil dibuat di database
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $this->user->id,
            'product_name' => 'Gundam RG 1/144',
            'amount' => 500000,
            'is_read' => false,
        ]);

        $initialCount = UserNotification::count();
        $this->assertEquals(1, $initialCount);

        // Hit index kedua kali -> sync dipicu lagi, pastikan TIDAK ada duplikasi
        $response2 = $this->getJson('/api/notifications');
        $response2->assertStatus(200);

        $newCount = UserNotification::count();
        $this->assertEquals(1, $newCount, 'Terdapat notifikasi duplikat setelah sync berulang!');
    }

    /**
     * Test bulk marking notifications as read works correctly.
     */
    public function test_bulk_mark_read_updates_read_status(): void
    {
        Sanctum::actingAs($this->user);

        // Buat dummy notification
        $notification = UserNotification::create([
            'user_id' => $this->user->id,
            'category' => 'income',
            'title' => 'Pemasukan',
            'message' => 'Transaksi masuk baru',
            'marketplace_name' => 'Tokopedia',
            'product_name' => 'Standard Item',
            'amount' => 100000,
            'is_read' => false,
            'occurred_at' => Carbon::now('Asia/Jakarta'),
        ]);

        $this->assertFalse($notification->is_read);

        // Mark as read via API (response includes refreshed list for UI)
        $response = $this->postJson('/api/notifications/mark-read', [
            'ids' => [$notification->id],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('updated', 1);
        $response->assertJsonPath('notifications.0.status', 'read');
        $response->assertJsonPath('notifications.0.is_read', true);
        $response->assertJsonPath('notifications.0.unread', false);

        // Verifikasi terupdate di database
        $notification->refresh();
        $this->assertTrue($notification->is_read);
    }

    /**
     * Test that read notifications are ordered to the bottom of the list.
     */
    public function test_read_notifications_are_ordered_at_the_bottom(): void
    {
        Sanctum::actingAs($this->user);

        $now = Carbon::now('Asia/Jakarta');

        // Buat 3 mock transactions dengan interval waktu berbeda
        $tx1 = MockTransaction::create([
            'user_id' => $this->user->id,
            'marketplace_name' => 'Tokopedia',
            'product_name' => 'Item Terlama',
            'amount' => 10000,
            'type' => 'income',
            'status' => 'success',
            'transaction_date' => (clone $now)->subMinutes(30),
        ]);

        $tx2 = MockTransaction::create([
            'user_id' => $this->user->id,
            'marketplace_name' => 'Tokopedia',
            'product_name' => 'Item Menengah',
            'amount' => 20000,
            'type' => 'income',
            'status' => 'success',
            'transaction_date' => (clone $now)->subMinutes(20),
        ]);

        $tx3 = MockTransaction::create([
            'user_id' => $this->user->id,
            'marketplace_name' => 'Tokopedia',
            'product_name' => 'Item Terbaru',
            'amount' => 30000,
            'type' => 'income',
            'status' => 'success',
            'transaction_date' => (clone $now)->subMinutes(10),
        ]);

        // Sync via GET index
        $this->getJson('/api/notifications');

        // Cari notifikasi "Item Menengah" dan tandai sebagai dibaca
        $notifMenengah = UserNotification::where('product_name', 'Item Menengah')->firstOrFail();
        $this->postJson('/api/notifications/mark-read', [
            'ids' => [$notifMenengah->id],
        ]);

        // Ambil ulang list notifikasi
        $response = $this->getJson('/api/notifications');
        $response->assertStatus(200);

        $notifications = $response->json('notifications');

        // Urutan yang diharapkan:
        // 1. "Item Terbaru" (unread: true, waktu terbaru) -> Urutan ke-1
        // 2. "Item Terlama" (unread: true, waktu terlama) -> Urutan ke-2
        // 3. "Item Menengah" (unread: false / read, waktu menengah) -> Urutan ke-3 (paling bawah/terakhir karena sudah dibaca)
        $this->assertCount(3, $notifications);

        $this->assertEquals('Item Terbaru', $notifications[0]['product_name']);
        $this->assertTrue($notifications[0]['unread']);

        $this->assertEquals('Item Terlama', $notifications[1]['product_name']);
        $this->assertTrue($notifications[1]['unread']);

        $this->assertEquals('Item Menengah', $notifications[2]['product_name']);
        $this->assertFalse($notifications[2]['unread'], 'Notifikasi yang sudah dibaca harus berada di posisi paling bawah!');
    }
}
