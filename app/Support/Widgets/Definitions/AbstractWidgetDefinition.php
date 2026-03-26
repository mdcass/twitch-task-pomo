<?php

namespace App\Support\Widgets\Definitions;

use App\Enums\ExternalAuthProvider;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use App\Support\Widgets\WidgetDefinition;

abstract class AbstractWidgetDefinition implements WidgetDefinition
{
    public function label(): string
    {
        return $this->type()->label();
    }

    public function defaultName(): string
    {
        return $this->label();
    }

    public function schemaVersion(): int
    {
        return 1;
    }

    public function normalizeConfig(array $config): array
    {
        return array_replace_recursive($this->defaultConfig(), array_filter(
            $config,
            static fn (string|int $key): bool => is_string($key),
            ARRAY_FILTER_USE_KEY,
        ));
    }

    public function normalizeAppearance(array $appearance): array
    {
        return array_replace_recursive($this->defaultAppearance(), array_filter(
            $appearance,
            static fn (string|int $key): bool => is_string($key),
            ARRAY_FILTER_USE_KEY,
        ));
    }

    public function appearanceRules(): array
    {
        return [
            'title_alignment' => ['nullable', 'in:left,center'],
            'accent' => ['nullable', 'string', 'max:32'],
        ];
    }

    public function requiresProviderConnection(): bool
    {
        return $this->requiredProvider() !== null;
    }

    public function requiredProvider(): ?ExternalAuthProvider
    {
        return null;
    }

    /**
     * @param  array<int, mixed>  $items
     * @return list<string>
     */
    protected function normalizeItems(array $items): array
    {
        return array_values(array_filter(array_map(static fn (mixed $item): string => trim((string) $item), $items)));
    }

    protected function parseDate(mixed $value): ?CarbonInterface
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
