<?php

namespace App\Actions\Widgets;

use App\Enums\Models\WidgetLifecycleState;
use App\Models\User;
use App\Models\Widget;
use App\Support\Widgets\WidgetDefinitionRegistry;
use App\Support\Widgets\WidgetLifecycleResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdateWidget
{
    public function __construct(
        private readonly WidgetDefinitionRegistry $definitions,
        private readonly WidgetLifecycleResolver $lifecycleResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function update(User $user, Widget $widget, array $input): Widget
    {
        Gate::forUser($user)->authorize('update', $widget);

        $definition = $this->definitions->forType($widget->type);
        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'config' => ['required', 'array'],
            'appearance' => ['required', 'array'],
            'config.*' => ['nullable'],
            'appearance.*' => ['nullable'],
        ])->validate();

        $config = Validator::make(
            $definition->normalizeConfig($validated['config']),
            $definition->configRules(),
        )->validate();

        $appearance = Validator::make(
            $definition->normalizeAppearance($validated['appearance']),
            $definition->appearanceRules(),
        )->validate();

        $widget->fill([
            'name' => trim((string) $validated['name']),
            'schema_version' => $definition->schemaVersion(),
            'config' => $config,
            'appearance' => $appearance,
        ]);

        if ($widget->lifecycle_state !== WidgetLifecycleState::Archived) {
            $widget->lifecycle_state = $this->lifecycleResolver->resolve($widget);
        }

        $widget->save();

        return $widget->fresh();
    }
}
