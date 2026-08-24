<?php

namespace Tests\Feature\Api;

use App\Models\MobilePushToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobilePushTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_active_user_can_register_push_token(): void
    {
        $user = $this->activeUser('resident');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/push-tokens', [
            'installation_id' => 'android-test-installation-001',
            'fcm_token' => 'test-fcm-token-001',
            'device_name' => 'TabangNow Android',
            'platform' => 'android',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.installation_id', 'android-test-installation-001')
            ->assertJsonPath('data.platform', 'android');

        $token = MobilePushToken::query()->firstOrFail();

        $this->assertSame($user->id, $token->user_id);
        $this->assertSame('test-fcm-token-001', $token->fcm_token);
        $this->assertSame(hash('sha256', 'test-fcm-token-001'), $token->token_hash);
        $this->assertNull($token->revoked_at);
    }

    public function test_same_installation_is_rebound_to_the_current_account(): void
    {
        $firstUser = $this->activeUser('resident');
        $secondUser = $this->activeUser('tanod');

        Sanctum::actingAs($firstUser);
        $this->postJson('/api/v1/push-tokens', [
            'installation_id' => 'android-shared-installation',
            'fcm_token' => 'first-fcm-token',
            'platform' => 'android',
        ])->assertOk();

        Sanctum::actingAs($secondUser);
        $this->postJson('/api/v1/push-tokens', [
            'installation_id' => 'android-shared-installation',
            'fcm_token' => 'second-fcm-token',
            'platform' => 'android',
        ])->assertOk();

        $this->assertSame(1, MobilePushToken::query()->count());

        $token = MobilePushToken::query()->firstOrFail();
        $this->assertSame($secondUser->id, $token->user_id);
        $this->assertSame('second-fcm-token', $token->fcm_token);
        $this->assertNull($token->revoked_at);
    }

    public function test_user_can_revoke_only_their_current_installation(): void
    {
        $firstUser = $this->activeUser('resident');
        $secondUser = $this->activeUser('tanod');

        MobilePushToken::query()->create([
            'user_id' => $firstUser->id,
            'installation_id' => 'first-installation',
            'fcm_token' => 'first-token',
            'token_hash' => hash('sha256', 'first-token'),
            'platform' => 'android',
        ]);

        MobilePushToken::query()->create([
            'user_id' => $secondUser->id,
            'installation_id' => 'second-installation',
            'fcm_token' => 'second-token',
            'token_hash' => hash('sha256', 'second-token'),
            'platform' => 'android',
        ]);

        Sanctum::actingAs($firstUser);

        $this->deleteJson('/api/v1/push-tokens/current', [
            'installation_id' => 'first-installation',
        ])->assertOk();

        $this->assertNotNull(
            MobilePushToken::query()
                ->where('installation_id', 'first-installation')
                ->firstOrFail()
                ->revoked_at
        );

        $this->assertNull(
            MobilePushToken::query()
                ->where('installation_id', 'second-installation')
                ->firstOrFail()
                ->revoked_at
        );
    }

    private function activeUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'status' => true,
        ]);
    }
}
