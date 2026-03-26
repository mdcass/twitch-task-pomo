<?php

namespace Tests\Feature\Integrations;

use App\Enums\ExternalAuthProvider;
use App\Enums\Models\WidgetLifecycleState;
use App\Enums\TeamMemberRole;
use App\Livewire\Integrations\IntegrationIndex;
use App\Models\ProviderAuth;
use App\Models\User;
use App\Models\Widget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IntegrationLivewireTest extends TestCase
{
    use RefreshDatabase;

    public function test_integration_index_renders_owner_controls_and_member_read_only_state(): void
    {
        $owner = User::factory()->withStreamerTeam()->create();
        $member = User::factory()->create();

        $owner->currentTeam->users()->attach($member, ['role' => TeamMemberRole::Moderator->value]);
        $member->switchTeam($owner->currentTeam);

        ProviderAuth::factory()->for($owner)->create([
            'provider' => ExternalAuthProvider::Twitch,
            'provider_email' => 'owner@example.test',
        ]);
        $widget = Widget::factory()->forTeam($owner->currentTeam, $owner)->followerGoal()->create([
            'name' => 'Partner Push',
            'lifecycle_state' => WidgetLifecycleState::Ready,
        ]);
        $widget->followerGoalState()->create([
            'current_count' => 8,
            'frozen_at' => null,
            'last_followed_at' => null,
        ]);

        Livewire::actingAs($owner)
            ->test(IntegrationIndex::class)
            ->assertSee('Connected')
            ->assertSee('Disconnect Twitch')
            ->assertSee('Partner Push');

        Livewire::actingAs($member)
            ->test(IntegrationIndex::class)
            ->assertSee('Partner Push')
            ->assertDontSee('Connect Twitch')
            ->assertDontSee('Reconnect Twitch')
            ->assertDontSee('Disconnect Twitch');
    }

    public function test_integration_index_disconnects_supported_providers_for_team_owners_only(): void
    {
        $owner = User::factory()->withStreamerTeam()->create();
        $member = User::factory()->create();

        $owner->currentTeam->users()->attach($member, ['role' => TeamMemberRole::Moderator->value]);
        $member->switchTeam($owner->currentTeam);

        $providerAuth = ProviderAuth::factory()->for($owner)->spotify()->create();
        Widget::factory()->forTeam($owner->currentTeam, $owner)->spotifyNowPlaying()->create([
            'lifecycle_state' => WidgetLifecycleState::Ready,
        ]);

        Livewire::actingAs($owner)
            ->test(IntegrationIndex::class)
            ->call('disconnect', 'spotify')
            ->assertSee('Spotify disconnected.');

        $this->assertSoftDeleted('provider_auths', ['id' => $providerAuth->id]);
        $this->assertSame(
            WidgetLifecycleState::PendingConnection,
            $owner->currentTeam->widgets()->sole()->fresh()->lifecycle_state,
        );

        Livewire::actingAs($member)
            ->test(IntegrationIndex::class)
            ->call('disconnect', 'spotify')
            ->assertForbidden();
    }
}
