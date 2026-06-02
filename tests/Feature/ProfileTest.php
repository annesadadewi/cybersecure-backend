<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'seller@gmail.com',
            'password' => Hash::make('secret123'),
        ]);
    }

    public function test_profile_show_returns_registration_data(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/profile');

        $response->assertOk();
        $response->assertJsonPath('profile.name', $this->user->name);
        $response->assertJsonPath('profile.email', 'seller@gmail.com');
    }

    public function test_profile_update_name_email_and_phone(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->putJson('/api/profile', [
            'name' => 'Budi Santoso',
            'email' => 'budi.baru@gmail.com',
            'phone' => '081234567890',
        ]);

        $response->assertOk();
        $response->assertJsonPath('profile.name', 'Budi Santoso');
        $response->assertJsonPath('profile.email', 'budi.baru@gmail.com');
        $response->assertJsonPath('profile.phone', '081234567890');

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'email' => 'budi.baru@gmail.com',
            'phone' => '081234567890',
        ]);
    }

    public function test_profile_password_change_requires_current_password(): void
    {
        Sanctum::actingAs($this->user);

        $this->putJson('/api/profile/password', [
            'current_password' => 'wrong',
            'password' => 'newpass1',
            'password_confirmation' => 'newpass1',
        ])->assertStatus(422);

        $this->putJson('/api/profile/password', [
            'current_password' => 'secret123',
            'password' => 'newpass1',
            'password_confirmation' => 'newpass1',
        ])->assertOk();

        $this->user->refresh();
        $this->assertTrue(Hash::check('newpass1', $this->user->password));
    }

    public function test_profile_photo_upload(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->user);

        $tmp = tempnam(sys_get_temp_dir(), 'avatar');
        file_put_contents($tmp, base64_decode(
            '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCAABAAEDAREAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCfAA//2Q=='
        ));
        $file = new UploadedFile($tmp, 'avatar.jpg', 'image/jpeg', null, true);

        $response = $this->post('/api/profile/photo', [
            'photo' => $file,
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk();
        $this->assertNotNull($response->json('profile.profile_photo_url'));
        $this->user->refresh();
        $this->assertNotNull($this->user->profile_photo);
        Storage::disk('public')->assertExists($this->user->profile_photo);
    }
}
