<?php

namespace App\Providers;

use App\Http\Controllers\Auth\RegisterController;
use App\Models\Canvas;
use App\Models\Team;
use App\Models\Widget;
use App\Policies\CanvasPolicy;
use App\Policies\TeamPolicy;
use App\Policies\WidgetPolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Http\Controllers\RegisteredUserController as FortifyRegisteredUserController;
use SocialiteProviders\Discord\Provider as DiscordProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Spotify\Provider as SpotifyProvider;
use SocialiteProviders\Twitch\Provider as TwitchProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(FortifyRegisteredUserController::class, RegisterController::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Canvas::class, CanvasPolicy::class);
        Gate::policy(Team::class, TeamPolicy::class);
        Gate::policy(Widget::class, WidgetPolicy::class);

        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('discord', DiscordProvider::class);
            $event->extendSocialite('spotify', SpotifyProvider::class);
            $event->extendSocialite('twitch', TwitchProvider::class);
        });
    }
}
