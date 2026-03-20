<?php

namespace App\Support\Shell;

use InvalidArgumentException;

class AppShellLayout
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            'vertical' => [
                'navigation_type' => 'default',
                'navbar_horizontal_shape' => 'default',
                'top_nav_class' => 'navbar navbar-top fixed-top navbar-expand',
                'body_class' => null,
                'sidebar' => true,
                'top_navigation' => false,
                'dual_layer' => false,
                'slim' => false,
            ],
            'horizontal' => [
                'navigation_type' => 'horizontal',
                'navbar_horizontal_shape' => 'default',
                'top_nav_class' => 'navbar navbar-top fixed-top navbar-expand-lg',
                'body_class' => null,
                'sidebar' => false,
                'top_navigation' => true,
                'dual_layer' => false,
                'slim' => false,
            ],
            'combo' => [
                'navigation_type' => 'combo',
                'navbar_horizontal_shape' => 'default',
                'top_nav_class' => 'navbar navbar-top fixed-top navbar-expand-lg',
                'body_class' => null,
                'sidebar' => true,
                'top_navigation' => true,
                'dual_layer' => false,
                'slim' => false,
            ],
            'dual-nav' => [
                'navigation_type' => 'dual',
                'navbar_horizontal_shape' => 'default',
                'top_nav_class' => 'navbar navbar-top fixed-top navbar-expand-lg',
                'body_class' => null,
                'sidebar' => false,
                'top_navigation' => true,
                'dual_layer' => true,
                'slim' => false,
            ],
            'topnav-slim' => [
                'navigation_type' => 'default',
                'navbar_horizontal_shape' => 'slim',
                'top_nav_class' => 'navbar navbar-top navbar-slim fixed-top navbar-expand',
                'body_class' => 'nav-slim',
                'sidebar' => true,
                'top_navigation' => false,
                'dual_layer' => false,
                'slim' => true,
            ],
        ];
    }

    public static function default(): string
    {
        return 'vertical';
    }

    public static function normalize(string $layout): string
    {
        $normalized = trim($layout);

        if (! array_key_exists($normalized, self::definitions())) {
            throw new InvalidArgumentException("Unsupported authenticated layout variant [{$layout}].");
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    public static function definition(string $layout): array
    {
        $normalized = self::normalize($layout);

        return self::definitions()[$normalized];
    }
}
