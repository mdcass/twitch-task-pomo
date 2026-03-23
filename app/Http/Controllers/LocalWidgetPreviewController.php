<?php

namespace App\Http\Controllers;

use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class LocalWidgetPreviewController extends Controller
{
    public function index(): View
    {
        $now = now()->seconds(0);

        return view('local.widgets.index', [
            'defaultTaskPreviewUrl' => route('local.widgets.task-list', [
                'title' => 'Focus Queue',
                'pending' => ['Plan stream outline', 'Refine camera framing'],
                'completed' => ['Warm up intro scene'],
            ], false),
            'defaultFocusPreviewUrl' => route('local.widgets.pomodoro', [
                'title' => 'Deep Work Sprint',
                'state' => 'focus',
                'focus_minutes' => 25,
                'break_minutes' => 5,
                'ends_at' => $now->copy()->addMinutes(25)->toIso8601String(),
            ], false),
            'defaultPausedPreviewUrl' => route('local.widgets.pomodoro', [
                'title' => 'Deep Work Sprint',
                'state' => 'paused',
                'focus_minutes' => 25,
                'break_minutes' => 5,
                'remaining_seconds' => 12 * 60,
            ], false),
            'defaultFocusEndsAt' => $now->copy()->addMinutes(25)->format('Y-m-d\TH:i'),
            'defaultBreakEndsAt' => $now->copy()->addMinutes(5)->format('Y-m-d\TH:i'),
        ]);
    }

    public function taskList(Request $request): View
    {
        $validated = Validator::make($request->query(), [
            'title' => ['nullable', 'string', 'max:120'],
            'pending' => ['nullable', 'array'],
            'pending.*' => ['nullable', 'string', 'max:160'],
            'completed' => ['nullable', 'array'],
            'completed.*' => ['nullable', 'string', 'max:160'],
        ])->validate();

        $pendingItems = $this->normalizeTaskItems($validated['pending'] ?? []);
        $completedItems = $this->normalizeTaskItems($validated['completed'] ?? []);

        return view('local.widgets.task-list', [
            'title' => $validated['title'] ?? 'Task List',
            'pendingItems' => $pendingItems,
            'completedItems' => $completedItems,
        ]);
    }

    public function pomodoro(Request $request): View
    {
        $validator = Validator::make($request->query(), [
            'title' => ['nullable', 'string', 'max:120'],
            'state' => ['required', Rule::in(['focus', 'break', 'paused'])],
            'focus_minutes' => ['nullable', 'integer', 'min:1', 'max:180'],
            'break_minutes' => ['nullable', 'integer', 'min:1', 'max:60'],
            'ends_at' => ['required_unless:state,paused', 'date'],
            'remaining_seconds' => ['required_if:state,paused', 'integer', 'min:0', 'max:21600'],
        ]);

        if ($validator->fails()) {
            abort(422, $validator->errors()->first());
        }

        /** @var array{
         *     title?: string,
         *     state: string,
         *     focus_minutes?: int|string,
         *     break_minutes?: int|string,
         *     ends_at?: string,
         *     remaining_seconds?: int|string
         * } $validated
         */
        $validated = $validator->validated();

        $title = $validated['title'] ?? 'Pomodoro';
        $state = $validated['state'];
        $focusMinutes = (int) ($validated['focus_minutes'] ?? 25);
        $breakMinutes = (int) ($validated['break_minutes'] ?? 5);

        $endsAt = null;
        $countdownTarget = null;
        $remainingSeconds = 0;

        if ($state === 'paused') {
            $remainingSeconds = (int) $validated['remaining_seconds'];
        } else {
            $endsAt = Carbon::parse($validated['ends_at']);
            $countdownTarget = $endsAt->toIso8601String();
            $remainingSeconds = max(0, now()->diffInSeconds($endsAt, false));
        }

        return view('local.widgets.pomodoro', [
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
        ]);
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, string>
     */
    private function normalizeTaskItems(array $items): array
    {
        return Collection::make($items)
            ->map(static fn (mixed $item): string => trim((string) $item))
            ->filter()
            ->values()
            ->all();
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
            'focus' => 'bg-primary-subtle text-primary-emphasis',
            'break' => 'bg-success-subtle text-success-emphasis',
            'paused' => 'bg-warning-subtle text-warning-emphasis',
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
