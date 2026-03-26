<?php

namespace App\Support\Widgets;

use App\Enums\ExternalAuthProvider;
use App\Enums\Models\WidgetType;
use App\Models\Widget;

interface WidgetDefinition
{
    public function type(): WidgetType;

    public function label(): string;

    public function defaultName(): string;

    public function schemaVersion(): int;

    /**
     * @return array<string, mixed>
     */
    public function defaultConfig(): array;

    /**
     * @return array<string, mixed>
     */
    public function defaultAppearance(): array;

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function normalizeConfig(array $config): array;

    /**
     * @param  array<string, mixed>  $appearance
     * @return array<string, mixed>
     */
    public function normalizeAppearance(array $appearance): array;

    /**
     * @return array<string, mixed>
     */
    public function configRules(): array;

    /**
     * @return array<string, mixed>
     */
    public function appearanceRules(): array;

    public function editorView(): string;

    public function renderView(): string;

    /**
     * @param  array<string, mixed>  $previewSeed
     * @return array<string, mixed>
     */
    public function renderData(Widget $widget, array $previewSeed = []): array;

    public function requiresProviderConnection(): bool;

    public function requiredProvider(): ?ExternalAuthProvider;

    /**
     * @return array{width:int,height:int}
     */
    public function artboard(): array;
}
