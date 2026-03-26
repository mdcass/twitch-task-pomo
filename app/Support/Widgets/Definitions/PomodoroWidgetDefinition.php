<?php

namespace App\Support\Widgets\Definitions;

use App\Enums\Models\WidgetType;

class PomodoroWidgetDefinition extends AbstractWidgetDefinition
{
    public function type(): WidgetType
    {
        return WidgetType::Pomodoro;
    }

    public function defaultConfig(): array
    {
        return [
            'title' => 'Deep Work Sprint',
            'focus_minutes' => 25,
            'break_minutes' => 5,
            'state' => 'focus',
        ];
    }

    public function configRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'focus_minutes' => ['required', 'integer', 'min:1', 'max:180'],
            'break_minutes' => ['required', 'integer', 'min:1', 'max:60'],
            'state' => ['required', 'in:focus,break,paused'],
        ];
    }

    public function defaultAppearance(): array
    {
        return [
            'title_alignment' => 'center',
            'accent' => 'primary',
        ];
    }

    public function editorView(): string
    {
        return 'widgets.editor.pomodoro';
    }

    public function renderView(): string
    {
        return 'overlay.widgets.pomodoro';
    }

    public function artboard(): array
    {
        return ['width' => 520, 'height' => 320];
    }
}
