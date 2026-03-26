<?php

namespace Tests\Feature;

use App\Enums\ActivityEvent;
use App\Enums\ExternalAuthProvider;
use App\Enums\UserSettingKey;
use App\Models\Activity;
use App\Models\ProviderAuth;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_auths_support_the_planned_schema_and_encrypt_tokens(): void
    {
        $user = User::factory()->create();

        $providerAuth = $user->providerAuths()->create([
            'provider' => ExternalAuthProvider::Twitch,
            'provider_user_id' => '27463794',
            'provider_email' => 'streamer@example.test',
            'avatar_url' => 'https://cdn.example.test/avatars/streamer.png',
            'access_token' => 'access-token-secret',
            'refresh_token' => 'refresh-token-secret',
            'token_expires_at' => now()->addHour(),
            'scopes' => ['user:read:email'],
            'profile' => [
                'display_name' => 'StreamerName',
                'login' => 'streamername',
            ],
            'last_used_at' => now(),
        ]);

        $freshProviderAuth = $providerAuth->fresh();
        $rawProviderAuth = DB::table('provider_auths')->find($providerAuth->id);

        $this->assertSame(ExternalAuthProvider::Twitch, $freshProviderAuth?->provider);
        $this->assertSame('27463794', $freshProviderAuth?->provider_user_id);
        $this->assertSame('streamer@example.test', $freshProviderAuth?->provider_email);
        $this->assertSame('https://cdn.example.test/avatars/streamer.png', $freshProviderAuth?->avatar_url);
        $this->assertSame('access-token-secret', $freshProviderAuth?->access_token);
        $this->assertSame('refresh-token-secret', $freshProviderAuth?->refresh_token);
        $this->assertSame(['user:read:email'], $freshProviderAuth?->scopes);
        $this->assertSame([
            'display_name' => 'StreamerName',
            'login' => 'streamername',
        ], $freshProviderAuth?->profile);
        $this->assertNotNull($freshProviderAuth?->token_expires_at);
        $this->assertNotNull($freshProviderAuth?->last_used_at);
        $this->assertFalse($freshProviderAuth?->isRevoked());
        $this->assertSame('https://cdn.example.test/avatars/streamer.png', $rawProviderAuth->avatar_url);
        $this->assertNotSame('access-token-secret', $rawProviderAuth->access_token);
        $this->assertNotSame('refresh-token-secret', $rawProviderAuth->refresh_token);
    }

    public function test_provider_auths_use_soft_deletes_for_revocation(): void
    {
        $providerAuth = ProviderAuth::factory()->create();

        $providerAuth->delete();

        $deletedProviderAuth = ProviderAuth::withTrashed()->find($providerAuth->id);

        $this->assertNotNull($deletedProviderAuth);
        $this->assertTrue($deletedProviderAuth->trashed());
        $this->assertTrue($deletedProviderAuth->isRevoked());
    }

    public function test_provider_auths_enforce_unique_provider_identity_pairs(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        ProviderAuth::factory()->for($firstUser)->create([
            'provider' => ExternalAuthProvider::Discord,
            'provider_user_id' => 'discord-user-123',
        ]);

        $this->expectException(QueryException::class);

        ProviderAuth::factory()->for($secondUser)->create([
            'provider' => ExternalAuthProvider::Discord,
            'provider_user_id' => 'discord-user-123',
        ]);
    }

    public function test_provider_auths_require_access_tokens(): void
    {
        $user = User::factory()->create();

        $this->expectException(QueryException::class);

        $user->providerAuths()->create([
            'provider' => ExternalAuthProvider::Twitch,
            'provider_user_id' => '27463794',
            'access_token' => null,
        ]);
    }

    public function test_user_settings_persist_current_legal_acceptance_and_history_in_one_row(): void
    {
        $user = User::factory()->create();
        $termsAcceptedAt = now()->subMinute()->toIso8601String();
        $privacyAcceptedAt = now()->toIso8601String();

        $value = [
            'current' => [
                'terms_of_service_accepted_at' => $termsAcceptedAt,
                'privacy_policy_accepted_at' => $privacyAcceptedAt,
            ],
            'history' => [
                [
                    'document' => 'terms_of_service',
                    'version' => '2026-03-22',
                    'accepted_at' => $termsAcceptedAt,
                    'source' => 'register',
                    'source_metadata' => [
                        'route' => 'register',
                    ],
                ],
                [
                    'document' => 'privacy_policy',
                    'version' => '2026-03-22',
                    'accepted_at' => $privacyAcceptedAt,
                    'source' => 'register',
                    'source_metadata' => [
                        'route' => 'register',
                    ],
                ],
            ],
        ];

        $user->userSettings()->create([
            'key' => UserSettingKey::LegalAcceptanceHistory,
            'value' => $value,
        ]);

        $setting = $user->fresh()->userSettings->sole();

        $this->assertSame(UserSettingKey::LegalAcceptanceHistory, $setting->key);
        $this->assertSame($value, $setting->value);
    }

    public function test_user_settings_enforce_one_row_per_user_and_key(): void
    {
        $user = User::factory()->create();

        $user->userSettings()->create([
            'key' => UserSettingKey::LegalAcceptanceHistory,
            'value' => [
                'current' => [
                    'terms_of_service_accepted_at' => now()->toIso8601String(),
                    'privacy_policy_accepted_at' => now()->toIso8601String(),
                ],
                'history' => [],
            ],
        ]);

        $this->expectException(QueryException::class);

        $user->userSettings()->create([
            'key' => UserSettingKey::LegalAcceptanceHistory,
            'value' => [
                'current' => [
                    'terms_of_service_accepted_at' => now()->toIso8601String(),
                    'privacy_policy_accepted_at' => now()->toIso8601String(),
                ],
                'history' => [],
            ],
        ]);
    }

    public function test_activity_log_allows_explicit_team_override_and_scrubs_forbidden_properties(): void
    {
        $team = Team::factory()->create();

        $activity = Activity::log(
            ActivityEvent::AuthSocialCallbackFailed,
            [
                'provider' => ExternalAuthProvider::Discord->value,
                'flow' => 'register',
                'reason' => 'provider_callback_exception',
                'access_token' => 'secret-access',
                'refresh_token' => 'secret-refresh',
                'profile' => [
                    'username' => 'sensitive',
                ],
                'context' => [
                    'token' => 'nested-secret',
                    'safe' => 'kept',
                ],
            ],
            teamId: $team->id,
        );

        $this->assertNotNull($activity);
        $this->assertSame($team->id, $activity->team_id);
        $this->assertSame('discord', $activity->getExtraProperty('provider'));
        $this->assertSame('register', $activity->getExtraProperty('flow'));
        $this->assertSame('provider_callback_exception', $activity->getExtraProperty('reason'));
        $this->assertNull($activity->getExtraProperty('access_token'));
        $this->assertNull($activity->getExtraProperty('refresh_token'));
        $this->assertNull($activity->getExtraProperty('profile'));
        $this->assertNull(data_get($activity->properties->toArray(), 'context.token'));
        $this->assertSame('kept', data_get($activity->properties->toArray(), 'context.safe'));
    }
}
