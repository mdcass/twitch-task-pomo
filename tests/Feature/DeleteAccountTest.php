<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Laravel\Jetstream\Http\Livewire\DeleteUserForm;
use Livewire\Livewire;
use Tests\TestCase;

class DeleteAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_accounts_can_be_deleted(): void
    {
        if (! Features::hasAccountDeletionFeatures()) {
            $this->markTestSkipped('Account deletion is not enabled.');
        }

        $this->actingAs($user = User::factory()->withStreamerTeam()->create());
        $team = $user->currentTeam;

        Livewire::test(DeleteUserForm::class)
            ->set('password', 'password')
            ->call('deleteUser');

        $deletedUser = User::withTrashed()->find($user->id);
        $deletedTeam = Team::withTrashed()->find($team->id);

        $this->assertNotNull($deletedUser);
        $this->assertTrue($deletedUser->trashed());
        $this->assertSame($team->id, $deletedUser->current_team_id);
        $this->assertNotNull($deletedTeam);
        $this->assertTrue($deletedTeam->trashed());
        $this->assertSame($user->id, $deletedTeam->user_id);
    }

    public function test_correct_password_must_be_provided_before_account_can_be_deleted(): void
    {
        if (! Features::hasAccountDeletionFeatures()) {
            $this->markTestSkipped('Account deletion is not enabled.');
        }

        $this->actingAs($user = User::factory()->withStreamerTeam()->create());

        Livewire::test(DeleteUserForm::class)
            ->set('password', 'wrong-password')
            ->call('deleteUser')
            ->assertHasErrors(['password']);

        $this->assertNotNull($user->fresh());
        $this->assertFalse($user->fresh()->trashed());
    }
}
