<?php

namespace App\Support\Widgets;

use App\Enums\Models\WidgetPreviewStatus;
use Illuminate\Support\Carbon;

class RemoteWidgetPreviewResult
{
    public function __construct(
        public readonly WidgetPreviewStatus $status,
        public readonly ?string $message = null,
        public readonly ?Carbon $checkedAt = null,
    ) {}
}
