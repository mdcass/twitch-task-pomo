<?php

namespace App\Http\Controllers;

use App\Enums\Models\WidgetSourceKind;
use App\Models\WidgetInstance;
use App\Support\Widgets\BuiltInWidgetPageFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class OverlayWidgetController extends Controller
{
    public function __construct(
        private readonly BuiltInWidgetPageFactory $builtInWidgetPageFactory,
    ) {}

    public function show(Request $request, WidgetInstance $widgetInstance): View
    {
        return match ($widgetInstance->source_kind) {
            WidgetSourceKind::BuiltIn => $this->showBuiltInWidget($request, $widgetInstance),
            WidgetSourceKind::RemoteUrl => $this->showRemoteWidget($request, $widgetInstance),
        };
    }

    private function showBuiltInWidget(Request $request, WidgetInstance $widgetInstance): View
    {
        abort_unless($widgetInstance->type !== null, 404);

        return view(
            $this->builtInWidgetPageFactory->viewFor($widgetInstance),
            $this->builtInWidgetPageFactory->dataFor(
                $widgetInstance,
                previewSeed: $this->previewSeedFrom($request),
            ),
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
