<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke coverage for the mobile bearer-token API used by the Android/iOS apps.
 */
class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_mobile_login_issues_bearer_token_and_protects_endpoints(): void
    {
        // Unauthenticated access is rejected.
        $this->getJson('/api/mobile/auth/me')->assertUnauthorized();

        $login = $this->postJson('/api/mobile/auth/login', [
            'username' => 'yahya',
            'password' => 'Bassir@2030',
            'device_name' => 'Test Device',
        ]);

        $login->assertOk()
            ->assertJsonStructure(['token_type', 'access_token', 'expires_at', 'user' => ['username']]);

        $token = $login->json('access_token');
        $headers = ['Authorization' => "Bearer {$token}"];

        $this->getJson('/api/mobile/auth/me', $headers)
            ->assertOk()
            ->assertJsonPath('user.username', 'yahya');

        $this->getJson('/api/mobile/dashboard/summary', $headers)->assertOk();
        $this->getJson('/api/mobile/candidates', $headers)->assertOk();
        $this->getJson('/api/mobile/jobs', $headers)->assertOk();
    }

    public function test_mobile_login_rejects_invalid_credentials(): void
    {
        // The controller returns 422 (invalid credentials) for a bad password.
        $this->postJson('/api/mobile/auth/login', [
            'username' => 'yahya',
            'password' => 'wrong-password',
        ])->assertStatus(422);
    }

    public function test_inactive_user_cannot_obtain_token(): void
    {
        User::where('username', 'recruiter')->update(['is_active' => false]);

        // Inactive users are excluded from the lookup, so login is rejected as invalid credentials.
        $this->postJson('/api/mobile/auth/login', [
            'username' => 'recruiter',
            'password' => 'Bassir@2030',
        ])->assertStatus(422);
    }
}
