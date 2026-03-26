<?php

namespace Tests\Feature\Integrations;

use App\Enums\ExternalAuthProvider;
use App\Exceptions\DomainInvariantViolation;
use App\Models\ProviderAuth;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamProviderAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_team_owner_throws_an_invariant_violation(): void
    {
        $owner = User::factory()->withStreamerTeam()->create();
        $owner->delete();

        $this->expectException(DomainInvariantViolation::class);

        $owner->currentTeam()->withTrashed()->firstOrFail()->currentProviderAuth(ExternalAuthProvider::Twitch);
    }

    public function test_revoked_auth_is_ignored(): void
    {
        $owner = User::factory()->withStreamerTeam()->create();
        ProviderAuth::factory()->for($owner)->spotify()->revoked()->create();

        $resolved = $owner->currentTeam->currentProviderAuth(ExternalAuthProvider::Spotify);

        $this->assertNull($resolved);
    }

    public function test_underscoped_auth_is_ignored(): void
    {
        $owner = User::factory()->withStreamerTeam()->create();
        ProviderAuth::factory()->for($owner)->spotify()->create([
            'scopes' => ['user-read-email'],
        ]);

        $resolved = $owner->currentTeam->currentProviderAuth(ExternalAuthProvider::Spotify);

        $this->assertNull($resolved);
    }

    public function test_expired_but_refreshable_auth_remains_usable(): void
    {
        $owner = User::factory()->withStreamerTeam()->create();
        $providerAuth = ProviderAuth::factory()->for($owner)->spotify()->create([
            'access_token' => 'expired-access-token',
            'refresh_token' => 'refresh-token',
            'token_expires_at' => now()->subMinute(),
        ]);

        $resolved = $owner->currentTeam->currentProviderAuth(ExternalAuthProvider::Spotify);

        $this->assertTrue($resolved?->is($providerAuth));
        $this->assertTrue($owner->currentTeam->hasUsableProviderAuth(ExternalAuthProvider::Spotify));
    }

    public function test_latest_usable_auth_wins_over_a_newer_underscoped_row(): void
    {
        $owner = User::factory()->withStreamerTeam()->create();
        $usable = ProviderAuth::factory()->for($owner)->spotify()->create([
            'last_used_at' => now()->subMinutes(10),
            'access_token' => 'usable-access-token',
            'refresh_token' => 'refresh-token',
            'token_expires_at' => now()->addMinutes(10),
        ]);
        ProviderAuth::factory()->for($owner)->spotify()->create([
            'last_used_at' => now(),
            'access_token' => 'underscoped-access-token',
            'scopes' => ['user-read-email'],
            'token_expires_at' => now()->addMinutes(10),
        ]);

        $resolved = $owner->currentTeam->currentProviderAuth(ExternalAuthProvider::Spotify);

        $this->assertTrue($resolved?->is($usable));
    }
}
