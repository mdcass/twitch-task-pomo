<?php

namespace App\Testing\Auth;

use App\Enums\ExternalAuthProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Laravel\Socialite\Two\User as SocialiteUser;
use RuntimeException;

class TestingSocialiteProvider implements Provider
{
    /**
     * @var list<string>
     */
    private array $scopes = [];

    public function __construct(
        private readonly Request $request,
        private readonly string $provider,
    ) {}

    /**
     * @param  list<string>  $scopes
     */
    public function setScopes(array $scopes): self
    {
        $this->scopes = $scopes;

        return $this;
    }

    public function withConsent(): self
    {
        return $this;
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function with(array $parameters): self
    {
        return $this;
    }

    public function redirect(): RedirectResponse
    {
        return new RedirectResponse(route('testing.oauth.authorize', [
            'provider' => $this->provider,
        ], false));
    }

    public function user(): SocialiteUserContract
    {
        $provider = ExternalAuthProvider::from($this->provider);
        $scenario = $this->request->session()->pull(TestingSocialAuthScenario::sessionKey($provider));

        if (! is_string($scenario) || $scenario === '') {
            throw new RuntimeException("No testing OAuth scenario has been configured for [{$provider->value}].");
        }

        $attributes = TestingSocialAuthScenario::payload($provider, $scenario);

        return tap(new SocialiteUser(), function (SocialiteUser $user) use ($attributes): void {
            $user->map([
                'id' => $attributes['id'],
                'nickname' => $attributes['nickname'] ?? null,
                'name' => $attributes['name'] ?? null,
                'email' => $attributes['email'] ?? null,
                'avatar' => $attributes['avatar'] ?? null,
            ]);

            $user->setRaw($attributes['raw'] ?? []);
            $user->token = $attributes['token'] ?? null;
            $user->refreshToken = $attributes['refreshToken'] ?? null;
            $user->expiresIn = $attributes['expiresIn'] ?? null;
            $user->approvedScopes = $attributes['approvedScopes'] ?? $this->scopes;
        });
    }
}
