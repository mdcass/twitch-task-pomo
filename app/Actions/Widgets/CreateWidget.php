<?php

namespace App\Actions\Widgets;

use App\Enums\Models\WidgetLifecycleState;
use App\Enums\Models\WidgetType;
use App\Models\FollowerGoalState;
use App\Models\Team;
use App\Models\User;
use App\Models\Widget;
use App\Support\Widgets\WidgetDefinitionRegistry;
use App\Support\Widgets\WidgetLifecycleResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateWidget
{
    public function __construct(
        private readonly WidgetDefinitionRegistry $definitions,
        private readonly WidgetLifecycleResolver $lifecycleResolver,
    ) {}

    /**
     * @throws AuthorizationException
     */
    public function create(User $user, Team $team, WidgetType $type, ?string $name = null): Widget
    {
        Gate::forUser($user)->authorize('create', Widget::class);

        return DB::transaction(function () use ($user, $team, $type, $name): Widget {
            $definition = $this->definitions->forType($type);

            $widget = $team->widgets()->create([
                'created_by_user_id' => $user->id,
                'type' => $type,
                'name' => filled($name) ? trim((string) $name) : $definition->defaultName(),
                'schema_version' => $definition->schemaVersion(),
                'config' => $definition->defaultConfig(),
                'appearance' => $definition->defaultAppearance(),
                'lifecycle_state' => WidgetLifecycleState::Draft,
            ]);

            $widget->forceFill([
                'lifecycle_state' => $this->lifecycleResolver->resolve($widget),
            ])->save();

            if ($type === WidgetType::FollowerGoal) {
                FollowerGoalState::query()->firstOrCreate(
                    ['widget_id' => $widget->id],
                    ['current_count' => 0],
                );
            }

            return $widget->fresh();
        });
    }
}
