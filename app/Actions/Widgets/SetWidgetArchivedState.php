<?php

namespace App\Actions\Widgets;

use App\Enums\Models\WidgetLifecycleState;
use App\Models\User;
use App\Models\Widget;
use App\Support\Widgets\WidgetLifecycleResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

class SetWidgetArchivedState
{
    public function __construct(
        private readonly WidgetLifecycleResolver $lifecycleResolver,
    ) {}

    /**
     * @throws AuthorizationException
     */
    public function archive(User $user, Widget $widget): Widget
    {
        Gate::forUser($user)->authorize('delete', $widget);

        $widget->forceFill([
            'lifecycle_state' => WidgetLifecycleState::Archived,
        ])->save();

        return $widget->fresh();
    }

    /**
     * @throws AuthorizationException
     */
    public function restore(User $user, Widget $widget): Widget
    {
        Gate::forUser($user)->authorize('update', $widget);

        $widget->forceFill([
            'lifecycle_state' => $this->lifecycleResolver->resolve(
                $widget->forceFill(['lifecycle_state' => WidgetLifecycleState::Draft]),
            ),
        ])->save();

        return $widget->fresh();
    }
}
