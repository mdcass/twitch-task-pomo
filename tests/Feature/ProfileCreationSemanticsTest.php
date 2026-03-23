<?php

namespace Tests\Feature;

use App\Actions\Teams\CreateOwnedTeam;
use App\Enums\TeamType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileCreationSemanticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_and_streamer_profile_creation_use_type_aware_defaults_and_switch_current_team(): void
    {
        $user = User::factory()->create([
            'name' => 'Profile Owner',
        ]);

        $streamerTeam = app(CreateOwnedTeam::class)->create($user, TeamType::Streamer);
        $viewerTeam = app(CreateOwnedTeam::class)->create($user, TeamType::Viewer);

        $user->refresh();

        $this->assertSame(TeamType::Streamer, $streamerTeam->type);
        $this->assertSame("Profile's Streamer Profile", $streamerTeam->name);
        $this->assertSame(TeamType::Viewer, $viewerTeam->type);
        $this->assertSame("Profile's Viewer Profile", $viewerTeam->name);
        $this->assertSame($viewerTeam->id, $user->current_team_id);
    }
}
