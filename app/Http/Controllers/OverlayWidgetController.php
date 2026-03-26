<?php

namespace App\Http\Controllers;

use App\Enums\Models\WidgetSourceKind;
use App\Models\Widget;
use App\Models\WidgetInstance;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class OverlayWidgetController extends Controller
{
    public function show(Request $request, WidgetInstance $widgetInstance): View
    {
        return match ($widgetInstance->source_kind) {
            WidgetSourceKind::Proprietary => $this->showProprietaryWidget($request, $widgetInstance),
            WidgetSourceKind::RemoteUrl => $this->showRemoteWidget($request, $widgetInstance),
        };
    }

    private function showProprietaryWidget(Request $request, WidgetInstance $widgetInstance): View
    {
        $widget = $widgetInstance->widget;
        abort_unless($widget !== null, 404);
        $widget = $widget->loadMissing('followerGoalState', 'team.owner');

        return view(
            $widget->definition()->renderView(),
            $widget->definition()->renderData($widget, $this->previewSeedFrom($request)),
        );
    }

    public function showPublished(Widget $widget, string $key): View
    {
        abort_unless($widget->isPublished(), 404);
        abort_unless(hash_equals((string) $widget->publication_key, $key), 404);
        abort_unless($widget->isReadyForRuntime(), 404);

        $widget = $widget->loadMissing('followerGoalState', 'team.owner');

        return view(
            $widget->definition()->renderView(),
            $widget->definition()->renderData($widget),
        );
    }

    private function showRemoteWidget(Request $request, WidgetInstance $widgetInstance): View
    {
        abort_unless(is_string($widgetInstance->embed_url) && $widgetInstance->embed_url !== '', 404);

        return view('overlay.remote-widget-preview', [
            'widget' => $widgetInstance,
            'token' => (string) $request->query('token', ''),
            'parentOrigin' => rtrim((string) config('app.url'), '/'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function previewSeedFrom(Request $request): array
    {
        $seed = $request->query('seed');

        if (! is_string($seed) || $seed === '') {
            return [];
        }

        $decoded = json_decode($seed, true);

        if (! is_array($decoded)) {
            return [];
        }

        return array_filter(
            $decoded,
            static fn (string|int $key): bool => is_string($key),
            ARRAY_FILTER_USE_KEY,
        );
    }
}
