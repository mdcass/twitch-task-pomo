<?php

namespace Tests;

use Illuminate\Support\Arr;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use PHPUnit\Framework\Assert as PHPUnit;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        $this->registerLivewireTestMacros();
    }

    private function registerLivewireTestMacros(): void
    {
        if (Testable::hasMacro('assertSeeTextNormalized')) {
            return;
        }

        $normalizeWhitespace = static function (string $value): string {
            $normalized = preg_replace('/\s+/u', ' ', trim($value));

            return $normalized ?? trim($value);
        };

        Testable::macro('assertSeeTextNormalized', function ($values, bool $escape = true) use ($normalizeWhitespace) {
            $content = $normalizeWhitespace(strip_tags($this->html()));

            foreach (Arr::wrap($values) as $value) {
                $expected = $normalizeWhitespace((string) ($escape ? e($value) : $value));

                PHPUnit::assertStringContainsString($expected, $content);
            }

            return $this;
        });

        Testable::macro('assertDontSeeTextNormalized', function ($values, bool $escape = true) use ($normalizeWhitespace) {
            $content = $normalizeWhitespace(strip_tags($this->html()));

            foreach (Arr::wrap($values) as $value) {
                $expected = $normalizeWhitespace((string) ($escape ? e($value) : $value));

                PHPUnit::assertStringNotContainsString($expected, $content);
            }

            return $this;
        });
    }
}
