<?php

namespace App\Actions\WidgetInstances;

use App\Models\Canvas;
use App\Models\WidgetInstance;
use App\Support\Widgets\WidgetGeometryNormalizer;
use App\Support\Widgets\WidgetInstanceSpec;
use Illuminate\Support\Facades\DB;

class CreateWidgetInstance
{
    public function __construct(
        private readonly WidgetGeometryNormalizer $geometryNormalizer,
    ) {}

    public function create(Canvas $canvas, WidgetInstanceSpec $spec): WidgetInstance
    {
        $widgetInstance = DB::transaction(function () use ($canvas, $spec): WidgetInstance {
            $nextIndex = (int) $canvas->widgetInstances()->max('z_index') + 1;
            $geometry = $this->geometryNormalizer->normalize($canvas, $spec->geometry);

            return $canvas->widgetInstances()->create([
                'widget_id' => $spec->widgetId,
                'team_id' => $canvas->team_id,
                'source_kind' => $spec->sourceKind,
                'name' => $spec->name,
                'embed_url' => $spec->embedUrl,
                ...$geometry->toArray(),
                'z_index' => $nextIndex,
                'is_visible' => $spec->isVisible,
                'settings' => [
                    'editor_defaults' => $geometry->editorDefaults(),
                ],
                'preview_status' => $spec->previewStatus,
                'preview_message' => $spec->previewMessage,
                'preview_checked_at' => $spec->previewCheckedAt,
            ]);
        });

        return $widgetInstance->fresh(['widget']);
    }
}
