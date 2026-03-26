<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Widget;
use Illuminate\Auth\Access\HandlesAuthorization;

class WidgetPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    public function view(User $user, Widget $widget): bool
    {
        return $user->belongsToTeam($widget->team);
    }

    public function create(User $user): bool
    {
        return $user->currentTeam !== null && $user->ownsTeam($user->currentTeam);
    }

    public function update(User $user, Widget $widget): bool
    {
        return $user->ownsTeam($widget->team);
    }

    public function delete(User $user, Widget $widget): bool
    {
        return $user->ownsTeam($widget->team);
    }
}
