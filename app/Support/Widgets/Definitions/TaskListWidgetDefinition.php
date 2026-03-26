<?php

namespace App\Support\Widgets\Definitions;

use App\Enums\Models\WidgetType;
use App\Models\Widget;

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

    public function renderData(Widget $widget, array $previewSeed = []): array
    {
        $config = $this->normalizeConfig($widget->config ?? []);

        return [
            'title' => (string) $config['title'],
            'pendingItems' => $this->normalizeItems($config['pending'] ?? []),
            'completedItems' => $this->normalizeItems($config['completed'] ?? []),
        ];
    }

    public static function hydrateFieldsForEditor(array $config): array
    {
        $config['pending_text'] = implode("\n", $config['pending'] ?? []);
        $config['completed_text'] = implode("\n", $config['completed'] ?? []);

        return $config;
    }

    public static function normalizeEditorFields(array $config): array
    {
        $config['pending'] = self::normalizeMultilineList($config['pending_text'] ?? null);
        $config['completed'] = self::normalizeMultilineList($config['completed_text'] ?? null);

        unset($config['pending_text'], $config['completed_text']);

        return $config;
    }

    public function artboard(): array
    {
        return ['width' => 720, 'height' => 560];
    }

    /**
     * @return list<string>
     */
    private static function normalizeMultilineList(mixed $value): array
    {
        if (! is_string($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $item): string => trim($item),
            preg_split('/\R/', $value) ?: [],
        )));
    }
}
