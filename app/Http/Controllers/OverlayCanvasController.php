<?php

namespace App\Http\Controllers;

use App\Models\Canvas;
use App\Models\WidgetInstance;
use App\Support\Widgets\OverlayWidgetFrameUrlFactory;
use Illuminate\Contracts\View\View;

class OverlayCanvasController extends Controller
{
    public function __construct(
        private readonly OverlayWidgetFrameUrlFactory $overlayWidgetFrameUrlFactory,
    ) {}

    public function show(string $canvasUuid): View
    {
        $canvas = Canvas::query()
            ->where('uuid', $canvasUuid)
            ->with('orderedWidgetInstances')
            ->firstOrFail();

        return view('overlay.canvas', [
            'canvas' => $canvas,
            'widgetFrameUrls' => $canvas->orderedWidgetInstances
                ->mapWithKeys(fn (WidgetInstance $widget): array => [
                    $widget->id => $this->overlayWidgetFrameUrlFactory->runtimeUrl($widget),
                ])
                ->all(),
        ]);
    }
}
