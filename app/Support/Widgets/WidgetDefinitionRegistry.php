<?php

namespace App\Support\Widgets;

use App\Enums\Models\WidgetType;
use App\Support\Widgets\Definitions\FollowerGoalWidgetDefinition;
use App\Support\Widgets\Definitions\PomodoroWidgetDefinition;
use App\Support\Widgets\Definitions\SpotifyNowPlayingWidgetDefinition;
use App\Support\Widgets\Definitions\TaskListWidgetDefinition;
use InvalidArgumentException;

class WidgetDefinitionRegistry
{
    /**
     * @var array<string, WidgetDefinition>|null
     */
    private ?array $definitions = null;

    /**
     * @return list<WidgetDefinition>
     */
    public function all(): array
    {
        return array_values($this->definitions());
    }

    public function forType(WidgetType $type): WidgetDefinition
    {
        return $this->definitions()[$type->value]
            ?? throw new InvalidArgumentException("Missing widget definition [{$type->value}].");
    }

    /**
     * @return array<string, WidgetDefinition>
     */
    private function definitions(): array
    {
        if ($this->definitions !== null) {
            return $this->definitions;
        }

        return $this->definitions = collect([
            app(TaskListWidgetDefinition::class),
            app(PomodoroWidgetDefinition::class),
            app(FollowerGoalWidgetDefinition::class),
            app(SpotifyNowPlayingWidgetDefinition::class),
        ])->mapWithKeys(fn (WidgetDefinition $definition): array => [
            $definition->type()->value => $definition,
        ])->all();
    }
}
