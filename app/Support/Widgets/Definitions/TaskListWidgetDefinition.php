<?php

namespace App\Support\Widgets\Definitions;

use App\Enums\Models\WidgetType;

class TaskListWidgetDefinition extends AbstractWidgetDefinition
{
    public function type(): WidgetType
    {
        return WidgetType::TaskList;
    }

    public function defaultConfig(): array
    {
        return [
            'title' => 'Focus Queue',
            'pending' => ['Plan stream outline', 'Refine camera framing'],
            'completed' => ['Warm up intro scene'],
        ];
    }

    public function configRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'pending' => ['nullable', 'array'],
            'pending.*' => ['nullable', 'string', 'max:160'],
            'completed' => ['nullable', 'array'],
            'completed.*' => ['nullable', 'string', 'max:160'],
        ];
    }

    public function defaultAppearance(): array
    {
        return [
            'title_alignment' => 'left',
            'accent' => 'primary',
        ];
    }

    public function editorView(): string
    {
        return 'widgets.editor.task-list';
    }

    public function renderView(): string
    {
        return 'overlay.widgets.task-list';
    }

    public function artboard(): array
    {
        return ['width' => 720, 'height' => 560];
    }
}
