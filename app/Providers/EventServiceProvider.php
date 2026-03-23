<?php

namespace App\Providers;

use App\Listeners\Auth\LogFailedLogin;
use App\Listeners\Auth\LogSuccessfulLogin;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Failed::class => [
            LogFailedLogin::class,
        ],
        Login::class => [
            LogSuccessfulLogin::class,
        ],
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
