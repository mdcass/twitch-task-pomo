<?php

namespace App\Actions\WidgetInstances;

use App\Enums\Models\WidgetType;
use App\Actions\CanvasWidgets\AttachWidgetToCanvas;
use App\Actions\Widgets\CreateWidget;
use App\Models\Canvas;
use App\Models\User;
use App\Models\WidgetInstance;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

class CreateBuiltInWidget
{
    public function __construct(
        private readonly CreateWidget $createWidget,
        private readonly AttachWidgetToCanvas $attachWidgetToCanvas,
    ) {}

    /**
     * @throws AuthorizationException
     */
    public function create(User $user, Canvas $canvas, WidgetType $type): WidgetInstance
    {
        Gate::forUser($user)->authorize('update', $canvas);
        $widget = $this->createWidget->create($user, $canvas->team, $type);

        return $this->attachWidgetToCanvas->attach($user, $canvas, $widget)->fresh(['widget']);
    }
}
