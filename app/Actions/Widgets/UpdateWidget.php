<?php

namespace App\Actions\Widgets;

use App\Models\User;
use App\Models\Widget;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdateWidget
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function update(User $user, Widget $widget, array $input): Widget
    {
        Gate::forUser($user)->authorize('update', $widget);

        $definition = $widget->definition();
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

        $widget->applyEditorUpdate(
            (string) $validated['name'],
            $config,
            $appearance,
        );

        return $widget->fresh('followerGoalState');
    }
}
