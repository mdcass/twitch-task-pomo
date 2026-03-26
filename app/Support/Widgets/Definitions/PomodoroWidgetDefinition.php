<?php

namespace App\Support\Widgets\Definitions;

use App\Enums\Models\WidgetType;
use App\Models\Widget;
use Carbon\CarbonInterface;

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

    public function renderData(Widget $widget, array $previewSeed = []): array
    {
        $config = $this->normalizeConfig($widget->config ?? []);
        $state = (string) ($config['state'] ?? 'focus');
        $focusMinutes = (int) ($config['focus_minutes'] ?? 25);
        $breakMinutes = (int) ($config['break_minutes'] ?? 5);

        $endsAt = $this->parseEndsAt($config['ends_at'] ?? $previewSeed['ends_at'] ?? null);
        $countdownTarget = null;
        $remainingSeconds = 0;

        if (isset($config['remaining_seconds'])) {
            $remainingSeconds = max(0, (int) $config['remaining_seconds']);
            $state = 'paused';
        } elseif ($endsAt instanceof CarbonInterface) {
            $countdownTarget = $endsAt->toIso8601String();
            $remainingSeconds = max(0, now()->diffInSeconds($endsAt, false));
        } else {
            $fallbackMinutes = $state === 'break' ? $breakMinutes : $focusMinutes;
            $remainingSeconds = $fallbackMinutes * 60;
        }

        return [
            'title' => (string) $config['title'],
            'state' => $state,
            'focusMinutes' => $focusMinutes,
            'breakMinutes' => $breakMinutes,
            'endsAt' => $endsAt,
            'countdownTarget' => $countdownTarget,
            'remainingSeconds' => $remainingSeconds,
            'countdownDisplay' => $this->formatDuration($remainingSeconds),
            'stateLabel' => $this->stateLabel($state),
            'stateSummary' => $this->stateSummary($state, $focusMinutes, $breakMinutes, $endsAt, $remainingSeconds),
            'stateBadgeClass' => $this->stateBadgeClass($state),
        ];
    }

    public function artboard(): array
    {
        return ['width' => 520, 'height' => 320];
    }

    private function formatDuration(int $remainingSeconds): string
    {
        $minutes = intdiv(max($remainingSeconds, 0), 60);
        $seconds = max($remainingSeconds, 0) % 60;

        return str_pad((string) $minutes, 2, '0', STR_PAD_LEFT).':'.str_pad((string) $seconds, 2, '0', STR_PAD_LEFT);
    }

    private function stateLabel(string $state): string
    {
        return match ($state) {
            'focus' => 'Focus Session',
            'break' => 'Break Window',
            'paused' => 'Paused Timer',
        };
    }

    private function stateBadgeClass(string $state): string
    {
        return match ($state) {
            'focus' => 'is-focus',
            'break' => 'is-break',
            'paused' => 'is-paused',
        };
    }

    private function stateSummary(
        string $state,
        int $focusMinutes,
        int $breakMinutes,
        ?CarbonInterface $endsAt,
        int $remainingSeconds,
    ): string {
        return match ($state) {
            'focus' => sprintf(
                '%d minute focus block ending at %s.',
                $focusMinutes,
                $endsAt?->format('H:i') ?? '--:--',
            ),
            'break' => sprintf(
                '%d minute break block ending at %s.',
                $breakMinutes,
                $endsAt?->format('H:i') ?? '--:--',
            ),
            'paused' => sprintf(
                'Timer paused with %s remaining.',
                $this->formatDuration($remainingSeconds),
            ),
        };
    }

    private function parseEndsAt(mixed $value): ?CarbonInterface
    {
        return $this->parseDate($value);
    }
}
