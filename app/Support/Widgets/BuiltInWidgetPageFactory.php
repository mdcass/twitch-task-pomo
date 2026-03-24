<?php

namespace App\Support\Widgets;

use App\Enums\Models\WidgetType;
use App\Models\WidgetInstance;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class BuiltInWidgetPageFactory
{
    public function viewFor(WidgetInstance $widget): string
    {
        return $this->viewForType($widget->type);
    }

    public function viewForType(?WidgetType $type): string
    {
        return match ($type) {
            WidgetType::TaskList => 'overlay.widgets.task-list',
            WidgetType::Pomodoro => 'overlay.widgets.pomodoro',
            default => throw new InvalidArgumentException('Widget does not have a supported built-in page view.'),
        };
    }

    /**
     * @param  array<string, mixed>  $previewSeed
     * @return array<string, mixed>
     */
    public function dataFor(WidgetInstance $widget, array $previewSeed = []): array
    {
        return $this->dataForType($widget->type, $widget->settings ?? [], $previewSeed);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $previewSeed
     * @return array<string, mixed>
     */
    public function dataForType(?WidgetType $type, array $settings = [], array $previewSeed = []): array
    {
        return match ($type) {
            WidgetType::TaskList => [
                'title' => (string) ($settings['title'] ?? 'Task List'),
                'pendingItems' => $this->normalizeItems($settings['pending'] ?? []),
                'completedItems' => $this->normalizeItems($settings['completed'] ?? []),
            ],
            WidgetType::Pomodoro => $this->pomodoroData($settings, $previewSeed),
            default => throw new InvalidArgumentException('Widget does not have a supported built-in page view.'),
        };
    }

    /**
     * @param  array<int, mixed>  $items
     * @return list<string>
     */
    private function normalizeItems(array $items): array
    {
        return array_values(array_filter(array_map(static fn (mixed $item): string => trim((string) $item), $items)));
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $previewSeed
     * @return array<string, mixed>
     */
    private function pomodoroData(array $settings, array $previewSeed): array
    {
        $title = (string) ($settings['title'] ?? 'Pomodoro');
        $state = (string) ($settings['state'] ?? 'focus');
        $focusMinutes = (int) ($settings['focus_minutes'] ?? 25);
        $breakMinutes = (int) ($settings['break_minutes'] ?? 5);

        $endsAt = $this->parseEndsAt($settings['ends_at'] ?? $previewSeed['ends_at'] ?? null);
        $countdownTarget = null;
        $remainingSeconds = 0;

        if (isset($settings['remaining_seconds'])) {
            $remainingSeconds = max(0, (int) $settings['remaining_seconds']);
            $state = 'paused';
        } elseif ($endsAt instanceof CarbonInterface) {
            $countdownTarget = $endsAt->toIso8601String();
            $remainingSeconds = max(0, now()->diffInSeconds($endsAt, false));
        } else {
            $fallbackMinutes = $state === 'break' ? $breakMinutes : $focusMinutes;
            $remainingSeconds = $fallbackMinutes * 60;
        }

        return [
            'title' => $title,
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

    private function parseEndsAt(mixed $value): ?CarbonInterface
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
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
}
