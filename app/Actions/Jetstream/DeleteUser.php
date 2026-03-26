<?php

namespace App\Actions\Jetstream;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Jetstream\Contracts\DeletesUsers;

class DeleteUser implements DeletesUsers
{
    /**
     * Delete the given user.
     */
    public function delete(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $ownedTeams = $user->ownedTeams()->get();

            $user->deleteProfilePhoto();
            $user->tokens->each->delete();
            $user->delete();
            $this->deleteTeams($ownedTeams);
        });
    }

    /**
     * Delete the teams and team associations attached to the user.
     */
    protected function deleteTeams(iterable $teams): void
    {
        foreach ($teams as $team) {
            $team->delete();
        }
    }
}
