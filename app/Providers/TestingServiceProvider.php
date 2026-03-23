<?php

namespace App\Providers;

use App\Testing\Auth\TestingSocialiteFactory;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;

class TestingServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! $this->app->environment('testing')) {
            return;
        }

        $this->app->make(SocialiteFactory::class);
        $this->app->bind(SocialiteFactory::class, TestingSocialiteFactory::class);
        $this->app->forgetInstance(SocialiteFactory::class);

        if ($this->app->routesAreCached()) {
            return;
        }

        Route::middleware('web')->group(base_path('routes/testing.php'));
    }
}
