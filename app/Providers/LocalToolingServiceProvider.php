<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class LocalToolingServiceProvider extends ServiceProvider
{
    /**
     * Determine whether the provider should register local tooling routes.
     */
    public static function supportsEnvironment(string $environment): bool
    {
        return in_array($environment, ['local', 'testing'], true);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! self::supportsEnvironment($this->app->environment())) {
            return;
        }

        if ($this->app->routesAreCached()) {
            return;
        }

        Route::middleware('web')->group(base_path('routes/local.php'));
    }
}
