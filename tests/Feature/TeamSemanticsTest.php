<?php

namespace Tests\Feature;

use App\Enums\TeamMemberRole;
use App\Enums\TeamType;
use App\Exceptions\DomainInvariantViolation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TeamSemanticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_types_and_membership_roles_are_enum_casts(): void
    {
        $owner = User::factory()->withStreamerTeam()->create();
        $moderator = User::factory()->create();

        $team = $owner->currentTeam;
        $team->users()->attach($moderator, ['role' => TeamMemberRole::Moderator->value]);

        $team = $team->fresh()->load('users');
        $membership = $team->users->firstWhere('id', $moderator->id)?->membership;

        $this->assertSame(TeamType::Streamer, $team->type);
        $this->assertSame(TeamMemberRole::Moderator, $membership?->role);
    }

    public function test_team_policy_allows_members_to_view_but_only_owners_to_manage_integrations(): void
    {
        $owner = User::factory()->withStreamerTeam()->create();
        $moderator = User::factory()->create();

        $team = $owner->currentTeam;
        $team->users()->attach($moderator, ['role' => TeamMemberRole::Moderator->value]);
        $moderator->switchTeam($team);

        $this->assertTrue($owner->can('view', $team));
        $this->assertTrue($owner->can('manageIntegrations', $team));
        $this->assertTrue($moderator->can('view', $team));
        $this->assertFalse($moderator->can('manageIntegrations', $team));
    }

    public function test_current_team_can_be_missing_when_the_foreign_key_is_cleared(): void
    {
        $user = User::factory()->withStreamerTeam()->create();

        $user->forceFill([
            'current_team_id' => null,
        ])->save();

        $this->assertNull($user->fresh()->currentTeam);
    }

    public function test_soft_deleted_teams_keep_membership_rows(): void
    {
        $owner = User::factory()->withStreamerTeam()->create();
        $moderator = User::factory()->create();

        $team = $owner->currentTeam;
        $team->users()->attach($moderator, ['role' => TeamMemberRole::Moderator->value]);

        $owner->delete();
        $team->delete();

        $deletedTeam = Team::withTrashed()->find($team->id);

        $this->assertNotNull($deletedTeam);
        $this->assertTrue($deletedTeam->trashed());
        $this->assertDatabaseHas('team_user', [
            'team_id' => $team->id,
            'user_id' => $moderator->id,
            'role' => TeamMemberRole::Moderator->value,
        ]);
        $this->assertSame(1, DB::table('team_user')->where([
            'team_id' => $team->id,
            'user_id' => $moderator->id,
        ])->count());
    }

    public function test_team_deletion_is_blocked_while_any_active_user_still_points_at_it_as_current_team(): void
    {
        $owner = User::factory()->withStreamerTeam()->create();

        $this->expectException(DomainInvariantViolation::class);

        $owner->currentTeam->delete();
    }
}
