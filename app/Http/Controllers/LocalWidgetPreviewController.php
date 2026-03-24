<?php

namespace App\Http\Controllers;

use App\Actions\LocalWidgets\SpotifyWidgetService;
use App\Enums\Models\WidgetType;
use App\Support\Widgets\BuiltInWidgetPageFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class LocalWidgetPreviewController extends Controller
{
    public function __construct(
        private readonly BuiltInWidgetPageFactory $builtInWidgetPageFactory,
    ) {}

    public function index(Request $request, SpotifyWidgetService $spotify): View
    {
        $now = now()->seconds(0);
        $user = $request->user();
        $currentUserSpotifyAuth = $user !== null ? $spotify->currentUserConnection($user) : null;
        $latestSpotifyAuth = $spotify->latestConnection();

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
            'defaultSpotifyPreviewUrl' => route('local.widgets.spotify.show', absolute: false),
            'defaultFocusEndsAt' => $now->copy()->addMinutes(25)->format('Y-m-d\TH:i'),
            'defaultBreakEndsAt' => $now->copy()->addMinutes(5)->format('Y-m-d\TH:i'),
            'spotifyConfigured' => $spotify->isConfigured(),
            'currentUserSpotifyAuth' => $currentUserSpotifyAuth,
            'latestSpotifyAuth' => $latestSpotifyAuth,
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

        return view('local.widgets.task-list', [
            ...$this->builtInWidgetPageFactory->dataForType(WidgetType::TaskList, [
                'title' => $validated['title'] ?? 'Task List',
                'pending' => $validated['pending'] ?? [],
                'completed' => $validated['completed'] ?? [],
            ]),
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

        return view('local.widgets.pomodoro', [
            ...$this->builtInWidgetPageFactory->dataForType(WidgetType::Pomodoro, [
                'title' => $validated['title'] ?? 'Pomodoro',
                'state' => $validated['state'],
                'focus_minutes' => (int) ($validated['focus_minutes'] ?? 25),
                'break_minutes' => (int) ($validated['break_minutes'] ?? 5),
                'ends_at' => $validated['state'] === 'paused' ? null : Carbon::parse($validated['ends_at'])->toIso8601String(),
                'remaining_seconds' => $validated['state'] === 'paused' ? (int) $validated['remaining_seconds'] : null,
            ]),
        ]);
    }
}
