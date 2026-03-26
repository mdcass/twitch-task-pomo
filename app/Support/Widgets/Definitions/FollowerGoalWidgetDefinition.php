<?php

namespace App\Support\Widgets\Definitions;

use App\Enums\ExternalAuthProvider;
use App\Enums\Models\WidgetType;

class FollowerGoalWidgetDefinition extends AbstractWidgetDefinition
{
    public function type(): WidgetType
    {
        return WidgetType::FollowerGoal;
    }

    public function defaultConfig(): array
    {
        return [
            'title' => 'Follower Goal',
            'goal_target' => 50,
            'end_date' => now()->addMonth()->toDateString(),
            'sound_preset' => 'chime',
        ];
    }

    public function configRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'goal_target' => ['required', 'integer', 'min:1', 'max:100000'],
            'end_date' => ['nullable', 'date'],
            'sound_preset' => ['nullable', 'in:none,chime'],
        ];
    }

    public function defaultAppearance(): array
    {
        return [
            'title_alignment' => 'center',
            'accent' => 'success',
        ];
    }

    public function editorView(): string
    {
        return 'widgets.editor.follower-goal';
    }

    public function renderView(): string
    {
        return 'overlay.widgets.follower-goal';
    }

    public function requiredProvider(): ?ExternalAuthProvider
    {
        return ExternalAuthProvider::Twitch;
    }

    public function artboard(): array
    {
        return ['width' => 520, 'height' => 220];
    }
}
