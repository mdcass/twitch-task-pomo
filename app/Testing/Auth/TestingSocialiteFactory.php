<?php

namespace App\Testing\Auth;

use App\Enums\ExternalAuthProvider;
use Illuminate\Contracts\Container\Container;
use Laravel\Socialite\SocialiteManager;

class TestingSocialiteFactory extends SocialiteManager
{
    public function __construct(Container $container)
    {
        parent::__construct($container);
    }

    /**
     * Get an OAuth provider implementation.
     */
    public function driver($driver = null): mixed
    {
        if (is_string($driver) && in_array($driver, ExternalAuthProvider::values(), true)) {
            return new TestingSocialiteProvider(
                request: request(),
                provider: $driver,
            );
        }

        return parent::driver($driver);
    }
}
