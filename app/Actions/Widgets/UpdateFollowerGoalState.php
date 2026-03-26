<?php

namespace App\Actions\Widgets;

use App\Models\User;
use App\Models\Widget;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

class UpdateFollowerGoalState
{
    /**
     * @throws AuthorizationException
     */
    public function reset(User $user, Widget $widget): Widget
    {
        Gate::forUser($user)->authorize('update', $widget);
        abort_unless($widget->followerGoalState !== null, 404);

        $widget->followerGoalState()->update([
            'current_count' => 0,
            'frozen_at' => null,
            'last_followed_at' => null,
        ]);

        return $widget->fresh('followerGoalState');
    }
}
