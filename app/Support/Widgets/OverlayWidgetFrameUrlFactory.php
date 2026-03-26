<?php

namespace App\Support\Widgets;

use App\Enums\Models\WidgetSourceKind;
use App\Models\CanvasWidget;
use App\Models\WidgetInstance;
use App\Support\Routing\OriginUrlGenerator;

class OverlayWidgetFrameUrlFactory
{
    public function __construct(
        private readonly OriginUrlGenerator $originUrlGenerator,
    ) {}

    /**
     * @param  array<string, mixed>  $previewSeed
     */
    public function editorUrl(CanvasWidget|WidgetInstance $widget, array $previewSeed = []): ?string
    {
        if (! $this->canRenderInEditor($widget)) {
            return null;
        }

        $token = $this->editorToken($widget, $previewSeed);

        if (! is_string($token) || $token === '') {
            return null;
        }

        return $this->originUrlGenerator->signedOverlayRoute(
            'overlay.widgets.show',
            $this->routeParameters($widget, $token, $previewSeed),
        );
    }

    /**
     * @param  array<string, mixed>  $previewSeed
     */
    public function editorToken(CanvasWidget|WidgetInstance $widget, array $previewSeed = []): ?string
    {
        if (! $this->canRenderInEditor($widget)) {
            return null;
        }

        return sha1((string) json_encode([
            'canvas_widget_id' => $widget->id,
            'source_kind' => $widget->source_kind->value,
            'type' => $widget->type?->value,
            'embed_url' => $widget->source_kind === WidgetSourceKind::RemoteUrl ? $widget->previewUrl() : null,
            'widget_id' => $widget->widget_id,
            'seed' => $previewSeed,
        ]));
    }

    public function runtimeUrl(CanvasWidget|WidgetInstance $widget): ?string
    {
        if (! $this->canRenderInRuntime($widget)) {
            return null;
        }

        return $this->originUrlGenerator->signedOverlayRoute(
            'overlay.widgets.show',
            $this->routeParameters($widget),
        );
    }

    private function canRenderInEditor(CanvasWidget|WidgetInstance $widget): bool
    {
        return match ($widget->source_kind) {
            WidgetSourceKind::Proprietary => $widget->type !== null,
            WidgetSourceKind::RemoteUrl => is_string($widget->previewUrl()) && $widget->previewUrl() !== '',
        };
    }

    private function canRenderInRuntime(CanvasWidget|WidgetInstance $widget): bool
    {
        return match ($widget->source_kind) {
            WidgetSourceKind::Proprietary => $widget->type !== null
                && $widget->widget?->lifecycle_state?->value !== 'archived',
            WidgetSourceKind::RemoteUrl => is_string($widget->embed_url) && $widget->embed_url !== '',
        };
    }

    /**
     * @param  array<string, mixed>  $previewSeed
     * @return array<string, mixed>
     */
    private function routeParameters(
        CanvasWidget|WidgetInstance $widget,
        ?string $token = null,
        array $previewSeed = [],
    ): array {
        $parameters = [
            'widgetInstance' => $widget->id,
        ];

        if (is_string($token) && $token !== '') {
            $parameters['token'] = $token;
        }

        if ($previewSeed !== []) {
            $seedPayload = json_encode($previewSeed);

            if (is_string($seedPayload) && $seedPayload !== '') {
                $parameters['seed'] = $seedPayload;
            }
        }

        return $parameters;
    }
}
