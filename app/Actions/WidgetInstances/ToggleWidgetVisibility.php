<?php

namespace App\Actions\WidgetInstances;

use App\Models\User;
use App\Models\WidgetInstance;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

class ToggleWidgetVisibility
{
    /**
     * @throws AuthorizationException
     */
    public function toggle(User $user, WidgetInstance $widgetInstance): WidgetInstance
    {
        Gate::forUser($user)->authorize('update', $widgetInstance->canvas);

        $widgetInstance->forceFill([
            'is_visible' => ! $widgetInstance->is_visible,
        ])->save();

        return $widgetInstance->fresh();
    }
}
