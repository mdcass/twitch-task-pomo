<?php

namespace App\Support\Widgets;

use App\Actions\Integrations\SpotifyPlaybackService;
use App\Enums\Models\WidgetType;
use App\Models\Widget;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class WidgetRenderDataFactory
{
    public function __construct(
        private readonly BuiltInWidgetPageFactory $builtInPages,
        private readonly SpotifyPlaybackService $spotifyPlayback,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function forWidget(Widget $widget, array $options = []): array
    {
        return match ($widget->type) {
            WidgetType::TaskList => $this->builtInPages->dataForType($widget->type, $widget->config ?? []),
            WidgetType::Pomodoro => $this->builtInPages->dataForType(
                $widget->type,
                $widget->config ?? [],
                $options['previewSeed'] ?? [],
            ),
            WidgetType::FollowerGoal => $this->followerGoalData($widget),
            WidgetType::SpotifyNowPlaying => $this->spotifyData($widget),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function followerGoalData(Widget $widget): array
    {
        $config = $widget->config ?? [];
        $goal = max(1, (int) ($config['goal_target'] ?? 1));
        $count = (int) ($widget->followerGoalState?->current_count ?? 0);
        $endDate = $this->parseDate($config['end_date'] ?? null);

        return [
            'title' => (string) ($config['title'] ?? $widget->displayName()),
            'goalTarget' => $goal,
            'currentCount' => $count,
            'progressPercent' => min(100, (int) floor(($count / $goal) * 100)),
            'remainingCount' => max(0, $goal - $count),
            'endDate' => $endDate,
            'soundPreset' => (string) ($config['sound_preset'] ?? 'chime'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function spotifyData(Widget $widget): array
    {
        $config = $widget->config ?? [];
        $payload = $this->spotifyPlayback->payloadForTeam($widget->team);

        return [
            'title' => (string) ($config['title'] ?? $widget->displayName()),
            'showAlbumArt' => (bool) ($config['show_album_art'] ?? true),
            'payload' => $payload,
        ];
    }

    private function parseDate(mixed $value): ?CarbonInterface
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
}
