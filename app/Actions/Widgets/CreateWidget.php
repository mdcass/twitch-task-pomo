<?php

namespace App\Actions\Widgets;

use App\Enums\Models\WidgetType;
use App\Models\Team;
use App\Models\User;
use App\Models\Widget;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateWidget
{
    /**
     * @throws AuthorizationException
     */
    public function create(User $user, Team $team, WidgetType $type, ?string $name = null): Widget
    {
        Gate::forUser($user)->authorize('create', Widget::class);

        return DB::transaction(function () use ($user, $team, $type, $name): Widget {
            $definition = $type->definition();

            $widget = $team->widgets()->create([
                'created_by_user_id' => $user->id,
                'type' => $type,
                'name' => filled($name) ? trim((string) $name) : $definition->defaultName(),
                'schema_version' => $definition->schemaVersion(),
                'config' => $definition->defaultConfig(),
                'appearance' => $definition->defaultAppearance(),
                'lifecycle_state' => $definition->requiresProviderConnection()
                    ? \App\Enums\Models\WidgetLifecycleState::PendingConnection
                    : \App\Enums\Models\WidgetLifecycleState::Ready,
            ]);

            $widget->setRelation('team', $team);
            $widget->bootstrapForCreation();

            return $widget->fresh('followerGoalState');
        });
    }
}
