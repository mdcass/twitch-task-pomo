<?php

namespace App\Support\Widgets;

use App\Enums\Models\WidgetLifecycleState;
use App\Models\Widget;
use App\Support\Integrations\TeamProviderAuthResolver;

class WidgetLifecycleResolver
{
    public function __construct(
        private readonly WidgetDefinitionRegistry $definitions,
        private readonly TeamProviderAuthResolver $providerAuthResolver,
    ) {}

    public function resolve(Widget $widget): WidgetLifecycleState
    {
        if ($widget->lifecycle_state === WidgetLifecycleState::Archived) {
            return WidgetLifecycleState::Archived;
        }

        $definition = $this->definitions->forType($widget->type);
        $requiredProvider = $definition->requiredProvider();

        if ($requiredProvider !== null && ! $this->providerAuthResolver->isConnected($widget->team, $requiredProvider)) {
            return WidgetLifecycleState::PendingConnection;
        }

        return WidgetLifecycleState::Ready;
    }
}
