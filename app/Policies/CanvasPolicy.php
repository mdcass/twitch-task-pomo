<?php

namespace App\Policies;

use App\Models\Canvas;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CanvasPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    public function view(User $user, Canvas $canvas): bool
    {
        return $user->belongsToTeam($canvas->team);
    }

    public function create(User $user): bool
    {
        return $user->currentTeam !== null && $user->ownsTeam($user->currentTeam);
    }

    public function update(User $user, Canvas $canvas): bool
    {
        return $user->ownsTeam($canvas->team);
    }

    public function delete(User $user, Canvas $canvas): bool
    {
        return $user->ownsTeam($canvas->team);
    }

    public function restore(User $user, Canvas $canvas): bool
    {
        return $user->ownsTeam($canvas->team);
    }
}
