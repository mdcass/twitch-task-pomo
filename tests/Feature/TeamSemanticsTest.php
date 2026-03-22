<?php

namespace Tests\Feature;

use App\Enums\TeamMemberRole;
use App\Enums\TeamType;
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

    public function test_current_team_falls_back_to_the_first_owned_team_when_missing(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $team = $user->currentTeam;

        $user->forceFill([
            'current_team_id' => null,
        ])->save();

        $resolvedCurrentTeam = $user->fresh()->currentTeam;

        $this->assertNotNull($resolvedCurrentTeam);
        $this->assertTrue($resolvedCurrentTeam->is($team));
        $this->assertSame($team->id, $user->fresh()->current_team_id);
    }

    public function test_soft_deleted_teams_keep_membership_rows(): void
    {
        $owner = User::factory()->withStreamerTeam()->create();
        $moderator = User::factory()->create();

        $team = $owner->currentTeam;
        $team->users()->attach($moderator, ['role' => TeamMemberRole::Moderator->value]);

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
}
