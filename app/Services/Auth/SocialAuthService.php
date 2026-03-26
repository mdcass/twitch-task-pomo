<?php

namespace App\Services\Auth;

use App\Actions\Auth\CompleteRegistration;
use App\Actions\Auth\CompleteSocialRegistration;
use App\Enums\ActivityEvent;
use App\Enums\ExternalAuthProvider;
use App\Enums\OauthFlow;
use App\Models\Activity;
use App\Models\ProviderAuth;
use App\Models\User;
use App\Workflows\Auth\SocialAuthHandshakeWorkflow;
use App\Workflows\Auth\SocialRegistrationWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Throwable;

class SocialAuthService
{
    public function __construct(
        private readonly SocialiteFactory $socialite,
        private readonly CompleteSocialRegistration $completeSocialRegistration,
        private readonly CompleteRegistration $completeRegistration,
    ) {}

    /**
     * @param  array<string, string>|null  $legalAcceptance
     */
    public function redirect(
        Request $request,
        ExternalAuthProvider $provider,
        OauthFlow $flow,
        ?array $legalAcceptance = null,
        ?string $debugOverride = null,
    ): RedirectResponse {
        $workflow = new SocialAuthHandshakeWorkflow();

        $workflow->apply('start_redirect', [
            'provider' => $provider->value,
            'flow' => $flow->value,
            'legal_acceptance' => $flow === OauthFlow::Register ? $legalAcceptance : null,
            'debug_override' => $flow === OauthFlow::Register && $this->shouldUseDebugEmailOverride()
                ? $debugOverride
                : null,
        ]);
        $workflow->saveStore(useSession: true);

        return $this->driver($provider)->redirect();
    }

    public function callback(Request $request, ExternalAuthProvider $provider): RedirectResponse
    {
        $handshake = SocialAuthHandshakeWorkflow::fromSession();

        if (! $handshake instanceof SocialAuthHandshakeWorkflow) {
            Activity::log(ActivityEvent::AuthSocialSessionInvalid, [
                'provider' => $provider->value,
                'reason' => 'handshake_missing',
            ]);

            return redirect()->route('login')->withErrors([
                'social' => 'Your '.$provider->label().' sign-in session expired. Please try again.',
            ]);
        }

        if ($handshake->getInitialContextValue('provider') !== $provider->value) {
            $this->clearWorkflowSession($request, SocialAuthHandshakeWorkflow::class);
            Activity::log(ActivityEvent::AuthSocialSessionInvalid, array_filter([
                'provider' => $provider->value,
                'flow' => OauthFlow::tryFrom((string) $handshake->getInitialContextValue('flow'))?->value,
                'reason' => 'provider_mismatch',
            ], fn (mixed $value): bool => $value !== null));

            return redirect()->route('login')->withErrors([
                'social' => 'Your '.$provider->label().' sign-in session expired. Please try again.',
            ]);
        }

        $flow = OauthFlow::tryFrom((string) $handshake->getInitialContextValue('flow')) ?? OauthFlow::Login;

        try {
            $providerUser = $this->driver($provider)->user();
        } catch (Throwable) {
            $handshake->apply('fail_callback', [
                'provider' => $provider->value,
                'flow' => $flow->value,
            ]);
            $handshake->close()->saveStore();
            $this->clearWorkflowSession($request, SocialAuthHandshakeWorkflow::class);
            Activity::log(ActivityEvent::AuthSocialCallbackFailed, [
                'provider' => $provider->value,
                'flow' => $flow->value,
                'reason' => 'provider_callback_exception',
            ]);

            return $this->redirectForFlow($flow, [
                'social' => 'We could not complete your '.$provider->label().' sign-in. Please try again.',
            ]);
        }

        if (! $this->hasAccessToken($providerUser)) {
            $handshake->apply('fail_callback', [
                'provider' => $provider->value,
                'flow' => $flow->value,
                'reason' => 'missing_access_token',
            ]);
            $handshake->close()->saveStore();
            $this->clearWorkflowSession($request, SocialAuthHandshakeWorkflow::class);
            Activity::log(ActivityEvent::AuthSocialCallbackFailed, [
                'provider' => $provider->value,
                'flow' => $flow->value,
                'reason' => 'missing_access_token',
            ]);

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
            try {
                $this->updateProviderAuth($providerAuth, $providerUser);
            } catch (ValidationException) {
                $handshake->apply('fail_callback', [
                    'provider' => $provider->value,
                    'flow' => $flow->value,
                    'reason' => 'missing_access_token',
                ]);
                $handshake->close()->saveStore();
                $this->clearWorkflowSession($request, SocialAuthHandshakeWorkflow::class);
                Activity::log(ActivityEvent::AuthSocialCallbackFailed, [
                    'provider' => $provider->value,
                    'flow' => $flow->value,
                    'reason' => 'missing_access_token',
                ]);

                return $this->redirectForFlow($flow, [
                    'social' => 'We could not complete your '.$provider->label().' sign-in. Please try again.',
                ]);
            }
            $handshake->apply('complete_login', [
                'provider_auth_id' => $providerAuth->id,
                'user_id' => $providerAuth->user->id,
            ]);
            $handshake->close()->saveStore();
            $this->clearWorkflowSession($request, SocialAuthHandshakeWorkflow::class);
            $this->login($request, $providerAuth->user);
            Activity::log(ActivityEvent::AuthSocialLoginSucceeded, [
                'provider' => $provider->value,
                'flow' => OauthFlow::Login->value,
            ], subject: $providerAuth->fresh(), causer: $providerAuth->user);

            return redirect()->intended(route('dashboard'));
        }

        if ($flow !== OauthFlow::Register) {
            $handshake->apply('fail_callback', [
                'provider' => $provider->value,
                'flow' => $flow->value,
                'reason' => 'provider_auth_missing',
            ]);
            $handshake->close()->saveStore();
            $this->clearWorkflowSession($request, SocialAuthHandshakeWorkflow::class);
            Activity::log(ActivityEvent::AuthSocialLoginMissingLink, [
                'provider' => $provider->value,
                'flow' => OauthFlow::Login->value,
                'reason' => 'provider_auth_missing',
            ]);

            return redirect()->route('register')->withErrors([
                'social' => 'No '.$provider->label().' account is linked here yet. Start from registration to create a new account.',
            ]);
        }

        $providerEmail = $this->resolveEffectiveProviderEmail(
            providerUser: $providerUser,
            debugOverride: $this->resolveDebugOverride($handshake),
        );

        if ($providerEmail === null) {
            return $this->startOnboardingWorkflow(
                request: $request,
                handshake: $handshake,
                provider: $provider,
                providerUser: $providerUser,
                providerUserId: $providerUserId,
                providerEmail: null,
            );
        }

        if ($this->completeSocialRegistration->emailBelongsToExistingUser($providerEmail)) {
            return $this->startOnboardingWorkflow(
                request: $request,
                handshake: $handshake,
                provider: $provider,
                providerUser: $providerUser,
                providerUserId: $providerUserId,
                providerEmail: $providerEmail,
                startBlocked: true,
            );
        }

        try {
            $user = $this->completeSocialRegistration->complete([
                'name' => $this->resolveDisplayName($providerUser, $providerEmail, $provider),
                'email' => $providerEmail,
                'provider' => $provider,
                'provider_user_id' => $providerUserId,
                'provider_email' => $providerEmail,
                'avatar_url' => $this->resolveAvatarUrl($providerUser),
                'access_token' => $providerUser->token,
                'refresh_token' => $providerUser->refreshToken,
                'expires_in' => $providerUser->expiresIn !== null ? (int) $providerUser->expiresIn : null,
                'scopes' => $this->resolveApprovedScopes($providerUser),
                'profile' => $this->resolveProfile($providerUser),
                'legal_acceptance' => $this->resolveLegalAcceptance($handshake),
            ]);
        } catch (ValidationException) {
            $handshake->apply('fail_callback', [
                'provider' => $provider->value,
                'flow' => $flow->value,
                'reason' => 'missing_access_token',
            ]);
            $handshake->close()->saveStore();
            $this->clearWorkflowSession($request, SocialAuthHandshakeWorkflow::class);
            Activity::log(ActivityEvent::AuthSocialCallbackFailed, [
                'provider' => $provider->value,
                'flow' => $flow->value,
                'reason' => 'missing_access_token',
            ]);

            return $this->redirectForFlow($flow, [
                'social' => 'We could not complete your '.$provider->label().' sign-in. Please try again.',
            ]);
        }

        $handshake->apply('complete_registration', [
            'registered_user_id' => $user->id,
            'registered_email' => $user->email,
        ]);
        $handshake->close()->saveStore();
        $this->clearWorkflowSession($request, SocialAuthHandshakeWorkflow::class);

        $this->completeRegistration->handle($request, $user);
        $properties = [
            'provider' => $provider->value,
            'flow' => OauthFlow::Register->value,
        ];

        if (($legalAcceptance = $this->resolveLegalAcceptance($handshake)) !== null) {
            $properties['legal_acceptance'] = [
                'terms_of_service_accepted_at' => $legalAcceptance['terms_of_service_accepted_at'] ?? null,
                'privacy_policy_accepted_at' => $legalAcceptance['privacy_policy_accepted_at'] ?? null,
                'source' => 'registration',
            ];
        }

        Activity::log(ActivityEvent::AuthSocialRegistrationCompleted, $properties, subject: $user->fresh(), causer: $user);

        return redirect()->route('verification.notice');
    }

    private function driver(ExternalAuthProvider $provider)
    {
        $driver = $this->socialite->driver($provider->value)->setScopes($provider->authScopes());

        if ($provider === ExternalAuthProvider::Discord) {
            $driver->withConsent();
        }

        return $driver;
    }

    public function shouldUseDebugEmailOverride(): bool
    {
        return app()->isLocal();
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
            'avatar_url' => $this->resolveAvatarUrl($providerUser),
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

    private function resolveDisplayName(SocialiteUser $providerUser, ?string $providerEmail, ExternalAuthProvider $provider): string
    {
        return $providerUser->getName()
            ?? $providerUser->getNickname()
            ?? (is_string($providerEmail) ? Str::before($providerEmail, '@') : null)
            ?? $provider->label().' User';
    }

    private function resolveAvatarUrl(SocialiteUser $providerUser): ?string
    {
        return $this->normalizeHttpsUrl($providerUser->getAvatar());
    }

    private function normalizeEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $normalized = Str::lower(trim($email));

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeHttpsUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $normalized = trim($url);

        if ($normalized === '' || filter_var($normalized, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return strcasecmp((string) parse_url($normalized, PHP_URL_SCHEME), 'https') === 0
            ? $normalized
            : null;
    }

    private function resolveEffectiveProviderEmail(SocialiteUser $providerUser, ?string $debugOverride): ?string
    {
        if ($this->shouldUseDebugEmailOverride() && $debugOverride !== null) {
            return $debugOverride === 'no_email'
                ? null
                : $this->normalizeEmail($debugOverride);
        }

        return $this->normalizeEmail($providerUser->getEmail());
    }

    private function startOnboardingWorkflow(
        Request $request,
        SocialAuthHandshakeWorkflow $handshake,
        ExternalAuthProvider $provider,
        SocialiteUser $providerUser,
        string $providerUserId,
        ?string $providerEmail,
        bool $startBlocked = false,
    ): RedirectResponse {
        $workflow = new SocialRegistrationWorkflow();

        $context = [
            'provider' => $provider->value,
            'provider_label' => $provider->label(),
            'provider_user_id' => $providerUserId,
            'effective_provider_email' => $providerEmail,
            'avatar_url' => $this->resolveAvatarUrl($providerUser),
            'display_name' => $this->resolveDisplayName($providerUser, $providerEmail, $provider),
            'scopes' => $this->resolveApprovedScopes($providerUser),
            'profile' => $this->resolveProfile($providerUser),
            'access_token' => is_string($providerUser->token) && $providerUser->token !== '' ? Crypt::encryptString($providerUser->token) : null,
            'refresh_token' => is_string($providerUser->refreshToken) && $providerUser->refreshToken !== '' ? Crypt::encryptString($providerUser->refreshToken) : null,
            'expires_in' => $providerUser->expiresIn !== null ? (int) $providerUser->expiresIn : null,
            'legal_acceptance' => $this->resolveLegalAcceptance($handshake),
            'debug_override' => $this->resolveDebugOverride($handshake),
        ];

        if ($startBlocked && $providerEmail !== null) {
            $context['attempted_email'] = $providerEmail;
            $workflow->apply('show_existing_account_handoff', $context);
        } else {
            $workflow->apply('start_email_collection', $context);
        }

        $handshake->apply('handoff_registration', [
            'provider_user_id' => $providerUserId,
            'effective_provider_email' => $providerEmail,
            'blocked' => $startBlocked,
        ]);
        $handshake->close()->saveStore();
        $this->clearWorkflowSession($request, SocialAuthHandshakeWorkflow::class);

        $workflow->saveStore(useSession: true);

        if ($startBlocked) {
            Activity::log(ActivityEvent::AuthSocialRegistrationBlockedExistingEmail, [
                'provider' => $provider->value,
                'flow' => OauthFlow::Register->value,
                'reason' => 'existing_local_email_match',
                'workflow' => [
                    'class' => SocialRegistrationWorkflow::class,
                    'state' => 'existing_account_handoff',
                ],
            ]);
        }

        return redirect()->route('register.social-email');
    }

    /**
     * @return array<string, string>|null
     */
    private function resolveLegalAcceptance(SocialAuthHandshakeWorkflow $handshake): ?array
    {
        $legalAcceptance = $handshake->getInitialContextValue('legal_acceptance');

        return is_array($legalAcceptance) ? $legalAcceptance : null;
    }

    private function resolveDebugOverride(SocialAuthHandshakeWorkflow $handshake): ?string
    {
        $debugOverride = $handshake->getInitialContextValue('debug_override');

        return is_string($debugOverride) && $debugOverride !== ''
            ? $debugOverride
            : null;
    }

    private function clearWorkflowSession(Request $request, string $workflowClass): void
    {
        $request->session()->forget('workflow_store_id.'.$workflowClass);
    }

    private function hasAccessToken(SocialiteUser $providerUser): bool
    {
        return is_string($providerUser->token) && trim($providerUser->token) !== '';
    }

    private function login(Request $request, User $user): void
    {
        Auth::login($user);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }
    }
}
