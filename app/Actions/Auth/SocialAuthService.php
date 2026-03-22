<?php

namespace App\Actions\Auth;

use App\Enums\ExternalAuthProvider;
use App\Enums\OauthFlow;
use App\Enums\UserSettingKey;
use App\Models\ProviderAuth;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Jetstream\Jetstream;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Throwable;

class SocialAuthService
{
    private const SESSION_KEY = 'oauth.pending';

    public function __construct(
        private readonly SocialiteFactory $socialite,
    ) {}

    /**
     * @param  array<string, string>|null  $legalAcceptance
     */
    public function redirect(
        Request $request,
        ExternalAuthProvider $provider,
        OauthFlow $flow,
        ?array $legalAcceptance = null,
    ): RedirectResponse {
        $pendingAuth = [
            'provider' => $provider->value,
            'flow' => $flow->value,
        ];

        if ($flow === OauthFlow::Register && $legalAcceptance !== null) {
            $pendingAuth['legal_acceptance'] = $legalAcceptance;
        }

        $request->session()->put(self::SESSION_KEY, $pendingAuth);

        return $this->driver($provider)->redirect();
    }

    public function callback(Request $request, ExternalAuthProvider $provider): RedirectResponse
    {
        $pendingAuth = $request->session()->pull(self::SESSION_KEY);

        if (! is_array($pendingAuth) || ($pendingAuth['provider'] ?? null) !== $provider->value) {
            return redirect()->route('login')->withErrors([
                'social' => 'Your '.$provider->label().' sign-in session expired. Please try again.',
            ]);
        }

        $flow = OauthFlow::tryFrom($pendingAuth['flow'] ?? '') ?? OauthFlow::Login;

        try {
            $providerUser = $this->driver($provider)->user();
        } catch (Throwable) {
            return $this->redirectForFlow($flow, [
                'social' => 'We could not complete your '.$provider->label().' sign-in. Please try again.',
            ]);
        }

        $providerUserId = (string) $providerUser->getId();
        $providerAuth = ProviderAuth::query()
            ->where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->first();

        if ($providerAuth !== null && $providerAuth->user !== null) {
            $this->updateProviderAuth($providerAuth, $providerUser);
            $this->login($request, $providerAuth->user);

            return redirect()->intended(route('dashboard'));
        }

        if ($flow !== OauthFlow::Register) {
            return redirect()->route('register')->withErrors([
                'social' => 'No '.$provider->label().' account is linked here yet. Start from registration to create a new account.',
            ]);
        }

        $providerEmail = $this->normalizeEmail($providerUser->getEmail());

        if ($providerEmail === null) {
            return redirect()->route('register')->withErrors([
                'social' => $provider->label().' did not return an email address. Use email registration for now.',
            ]);
        }

        $matchingUserExists = User::query()
            ->whereRaw('LOWER(email) = ?', [$providerEmail])
            ->exists();

        if ($matchingUserExists) {
            return redirect()->route('login')->withErrors([
                'social' => 'That email already belongs to an existing account. Sign in with your original method, then link '.$provider->label().' from your account later.',
            ]);
        }

        $user = DB::transaction(function () use ($pendingAuth, $provider, $providerEmail, $providerUser, $providerUserId): User {
            $user = new User;
            $user->forceFill([
                'name' => $this->resolveDisplayName($providerUser, $providerEmail, $provider),
                'email' => $providerEmail,
                'email_verified_at' => now(),
                'password' => Hash::make(Str::password(32)),
            ])->save();

            $this->createProviderAuth($user, $provider, $providerUserId, $providerEmail, $providerUser);
            $this->recordLegalAcceptance($user, $pendingAuth['legal_acceptance'] ?? null);

            return $user;
        });

        $this->login($request, $user);

        return redirect()->intended(route('dashboard'));
    }

    private function driver(ExternalAuthProvider $provider)
    {
        $driver = $this->socialite->driver($provider->value)->setScopes($provider->authScopes());

        if ($provider === ExternalAuthProvider::Discord) {
            $driver->withConsent();
        }

        return $driver;
    }

    private function resolveFlow(Request $request): string
    {
        return Validator::make($request->query(), [
            'flow' => ['required', 'in:login,register'],
        ])->validated()['flow'];
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function redirectForFlow(OauthFlow $flow, array $errors): RedirectResponse
    {
        return redirect()->route($flow->routeName())->withErrors($errors);
    }

    private function updateProviderAuth(ProviderAuth $providerAuth, SocialiteUser $providerUser): void
    {
        $providerAuth->forceFill($this->providerAuthAttributes(
            $providerAuth->provider,
            (string) $providerUser->getId(),
            $this->normalizeEmail($providerUser->getEmail()),
            $providerUser,
        ));

        $providerAuth->save();
    }

    private function createProviderAuth(
        User $user,
        ExternalAuthProvider $provider,
        string $providerUserId,
        ?string $providerEmail,
        SocialiteUser $providerUser,
    ): void {
        $user->providerAuths()->create($this->providerAuthAttributes(
            $provider,
            $providerUserId,
            $providerEmail,
            $providerUser,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function providerAuthAttributes(
        ExternalAuthProvider $provider,
        string $providerUserId,
        ?string $providerEmail,
        SocialiteUser $providerUser,
    ): array {
        return [
            'provider' => $provider,
            'provider_user_id' => $providerUserId,
            'provider_email' => $providerEmail,
            'access_token' => $providerUser->token,
            'refresh_token' => $providerUser->refreshToken,
            'token_expires_at' => $providerUser->expiresIn !== null ? now()->addSeconds((int) $providerUser->expiresIn) : null,
            'scopes' => $this->resolveApprovedScopes($providerUser),
            'profile' => $this->resolveProfile($providerUser),
            'last_used_at' => now(),
        ];
    }

    /**
     * @return list<string>
     */
    private function resolveApprovedScopes(SocialiteUser $providerUser): array
    {
        $approvedScopes = $providerUser->approvedScopes ?? null;

        if (is_array($approvedScopes) && $approvedScopes !== []) {
            return array_values(array_filter($approvedScopes, 'is_string'));
        }

        $accessTokenResponseBody = $providerUser->accessTokenResponseBody ?? null;
        $scope = is_array($accessTokenResponseBody) ? ($accessTokenResponseBody['scope'] ?? null) : null;

        if (is_string($scope) && $scope !== '') {
            return preg_split('/\s+/', trim($scope)) ?: [];
        }

        if (is_array($scope)) {
            return array_values(array_filter($scope, 'is_string'));
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveProfile(SocialiteUser $providerUser): array
    {
        $raw = method_exists($providerUser, 'getRaw') ? $providerUser->getRaw() : [];

        return is_array($raw) ? $raw : [];
    }

    private function resolveDisplayName(SocialiteUser $providerUser, string $providerEmail, ExternalAuthProvider $provider): string
    {
        return $providerUser->getName()
            ?? $providerUser->getNickname()
            ?? Str::before($providerEmail, '@')
            ?? $provider->label().' User';
    }

    private function normalizeEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $normalized = Str::lower(trim($email));

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @param  array<string, string>|null  $legalAcceptance
     */
    private function recordLegalAcceptance(User $user, ?array $legalAcceptance): void
    {
        if (! Jetstream::hasTermsAndPrivacyPolicyFeature() || $legalAcceptance === null) {
            return;
        }

        $acceptedAt = now()->toIso8601String();
        $termsAcceptedAt = $legalAcceptance['terms_of_service_accepted_at'] ?? $acceptedAt;
        $privacyAcceptedAt = $legalAcceptance['privacy_policy_accepted_at'] ?? $acceptedAt;
        $version = now()->toDateString();

        $user->userSettings()->updateOrCreate(
            ['key' => UserSettingKey::LegalAcceptanceHistory],
            [
                'value' => [
                    'current' => [
                        'terms_of_service_accepted_at' => $termsAcceptedAt,
                        'privacy_policy_accepted_at' => $privacyAcceptedAt,
                    ],
                    'history' => [
                        [
                            'document' => 'terms_of_service',
                            'version' => $version,
                            'accepted_at' => $termsAcceptedAt,
                            'source' => 'social_register',
                            'source_metadata' => [
                                'provider' => 'socialite',
                                'route' => 'register',
                            ],
                        ],
                        [
                            'document' => 'privacy_policy',
                            'version' => $version,
                            'accepted_at' => $privacyAcceptedAt,
                            'source' => 'social_register',
                            'source_metadata' => [
                                'provider' => 'socialite',
                                'route' => 'register',
                            ],
                        ],
                    ],
                ],
            ],
        );
    }

    private function login(Request $request, User $user): void
    {
        Auth::login($user);
        $request->session()->regenerate();
    }
}
