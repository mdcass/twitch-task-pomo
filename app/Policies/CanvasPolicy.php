<?php

namespace App\Policies;

use App\Models\Canvas;
use App\Models\User;
use App\Models\Widget;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

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

    public function attachWidget(User $user, Canvas $canvas, Widget $widget): Response
    {
        if (! $this->update($user, $canvas)) {
            return Response::deny('You are not allowed to update this canvas.');
        }

        return $widget->team_id === $canvas->team_id
            ? Response::allow()
            : Response::deny('You may only attach widgets from the same team.');
    }
}
