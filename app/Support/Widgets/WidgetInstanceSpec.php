<?php

namespace App\Support\Widgets;

use App\Enums\Models\WidgetPreviewStatus;
use App\Enums\Models\WidgetSourceKind;
use Carbon\CarbonInterface;

final readonly class WidgetInstanceSpec
{
    public function __construct(
        public WidgetSourceKind $sourceKind,
        public WidgetGeometry $geometry,
        public ?int $widgetId = null,
        public ?string $name = null,
        public ?string $embedUrl = null,
        public WidgetPreviewStatus $previewStatus = WidgetPreviewStatus::Pending,
        public ?string $previewMessage = null,
        public ?CarbonInterface $previewCheckedAt = null,
        public bool $isVisible = true,
    ) {}
}
