<?php

namespace App\Support\Widgets\Definitions;

use App\Enums\ExternalAuthProvider;
use App\Enums\Models\WidgetType;
use App\Models\Widget;

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

    public function renderData(Widget $widget, array $previewSeed = []): array
    {
        $config = $this->normalizeConfig($widget->config ?? []);
        $goalTarget = max(1, (int) ($config['goal_target'] ?? 1));
        $currentCount = (int) $widget->followerGoalStateOrFail()->current_count;

        return [
            'title' => (string) $config['title'],
            'goalTarget' => $goalTarget,
            'currentCount' => $currentCount,
            'progressPercent' => min(100, (int) floor(($currentCount / $goalTarget) * 100)),
            'remainingCount' => max(0, $goalTarget - $currentCount),
            'endDate' => $this->parseDate($config['end_date'] ?? null),
            'soundPreset' => (string) ($config['sound_preset'] ?? 'chime'),
        ];
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
