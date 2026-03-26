<?php

namespace App\Services\Integrations;

use App\Enums\ExternalAuthProvider;
use App\Models\Activity;
use App\Models\ProviderAuth;
use App\Models\Stream;
use App\Models\Team;
use App\Models\User;
use App\Models\Widget;
use App\Workflows\Widgets\IntegrationConnectionWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Throwable;

class IntegrationConnectionService
{
    public function __construct(
        private readonly SocialiteFactory $socialite,
    ) {}

    public function redirect(Request $request, ExternalAuthProvider $provider, ?Widget $widget = null): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $this->authorizeIntegrationManagement($user);

        if ($widget !== null) {
            Gate::forUser($user)->authorize('update', $widget);
        }

        $workflow = new IntegrationConnectionWorkflow(subject: $widget);
        $workflow->apply('start_redirect', [
            'provider' => $provider->value,
            'return_to' => $this->returnTo($request, $widget),
            'widget_id' => $widget?->id,
        ]);
        $workflow->saveStore(useSession: true);

        return $this->driver($provider)->redirect();
    }

    public function callback(Request $request, ExternalAuthProvider $provider): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $team = $this->authorizeIntegrationManagement($user);

        $workflow = IntegrationConnectionWorkflow::fromSession();
        abort_unless($workflow instanceof IntegrationConnectionWorkflow, 404);

        try {
            $providerUser = $this->driver($provider)->user();
        } catch (Throwable) {
            return $this->failCallback($workflow, $provider);
        }

        if (! $this->hasAccessToken($providerUser)) {
            return $this->failCallback($workflow, $provider, [
                'reason' => 'missing_access_token',
            ]);
        }

        $auth = $this->persistProviderAuth($user, $provider, $providerUser);

        if ($provider === ExternalAuthProvider::Twitch) {
            $this->upsertTwitchStream($team, $auth, $providerUser);
        }

        $workflow->apply('complete_connection', [
            'provider' => $provider->value,
            'provider_auth_id' => $auth->id,
        ]);
        $workflow->close()->saveStore();
        session()->forget('workflow_store_id.'.IntegrationConnectionWorkflow::class);

        $this->refreshTeamWidgetLifecycle($team);

        Activity::withCauser($user, function () use ($provider, $auth): void {
            Activity::log(
                event: \App\Enums\ActivityEvent::ProviderAuthUpdated,
                properties: ['provider' => $provider->value],
                subject: $auth,
                causer: Auth::user(),
                logName: 'widget',
            );
        });

        return redirect($this->returnToFromWorkflow($workflow))
            ->with('status', $provider->label().' connected.');
    }

    public function disconnect(User $user, ExternalAuthProvider $provider): void
    {
        $team = $this->authorizeIntegrationManagement($user);

        $ownerAuth = $team->currentProviderAuth($provider);

        if ($ownerAuth !== null) {
            $ownerAuth->delete();
        }

        $this->refreshTeamWidgetLifecycle($team);
    }

    private function driver(ExternalAuthProvider $provider)
    {
        $driver = $this->socialite
            ->driver($provider->value)
            ->redirectUrl(route('integrations.callback', ['provider' => $provider->value]))
            ->setScopes($provider->authScopes());

        if ($provider === ExternalAuthProvider::Spotify) {
            $driver->with(['show_dialog' => 'true']);
        }

        return $driver;
    }

    private function persistProviderAuth(User $user, ExternalAuthProvider $provider, SocialiteUser $providerUser): ProviderAuth
    {
        $providerUserId = trim((string) $providerUser->getId());
        abort_unless($providerUserId !== '', 422);

        $providerAuth = ProviderAuth::withTrashed()
            ->where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->first() ?? new ProviderAuth();

        $providerAuth->user()->associate($user);
        $providerAuth->forceFill([
            'provider' => $provider,
            'provider_user_id' => $providerUserId,
            'provider_email' => $this->normalizeEmail($providerUser->getEmail()),
            'avatar_url' => $this->normalizeAvatarUrl($providerUser->getAvatar()),
            'access_token' => $providerUser->token,
            'refresh_token' => $providerUser->refreshToken,
            'token_expires_at' => $providerUser->expiresIn ? now()->addSeconds((int) $providerUser->expiresIn) : null,
            'scopes' => $this->normalizeScopes($providerUser->approvedScopes ?? data_get($providerUser->accessTokenResponseBody, 'scope') ?? $provider->authScopes()),
            'profile' => method_exists($providerUser, 'getRaw') && is_array($providerUser->getRaw()) ? $providerUser->getRaw() : [],
            'last_used_at' => now(),
        ]);
        $providerAuth->save();

        if ($providerAuth->trashed()) {
            $providerAuth->restore();
        }

        ProviderAuth::query()
            ->where('user_id', $user->id)
            ->where('provider', $provider)
            ->whereKeyNot($providerAuth->id)
            ->get()
            ->each
            ->delete();

        return $providerAuth->fresh();
    }

    private function upsertTwitchStream(Team $team, ProviderAuth $providerAuth, SocialiteUser $providerUser): Stream
    {
        $profile = method_exists($providerUser, 'getRaw') && is_array($providerUser->getRaw()) ? $providerUser->getRaw() : [];

        return $team->streams()->updateOrCreate([
            'provider' => ExternalAuthProvider::Twitch,
        ], [
            'provider_auth_id' => $providerAuth->id,
            'provider' => ExternalAuthProvider::Twitch,
            'provider_channel_id' => (string) ($providerUser->getId() ?? data_get($profile, 'id')),
            'channel_login' => (string) (data_get($profile, 'login') ?? $providerUser->getNickname() ?? Str::lower((string) ($providerUser->getName() ?? 'twitch-stream'))),
            'display_name' => (string) (data_get($profile, 'display_name') ?? $providerUser->getName() ?? $providerUser->getNickname() ?? 'Twitch Stream'),
            'metadata' => $profile,
        ]);
    }

    private function refreshTeamWidgetLifecycle(Team $team): void
    {
        $team->widgets()
            ->get()
            ->each(function (Widget $widget): void {
                $widget->refreshLifecycle();
            });
    }

    private function authorizeIntegrationManagement(User $user): Team
    {
        $team = $user->currentTeam;
        abort_unless($team instanceof Team, 403);

        Gate::forUser($user)->authorize('manageIntegrations', $team);

        return $team;
    }

    private function returnTo(Request $request, ?Widget $widget): string
    {
        $returnTo = $request->query('return_to');

        if (is_string($returnTo) && str_starts_with($returnTo, '/')) {
            return $returnTo;
        }

        if ($widget !== null) {
            return route('widgets.edit', $widget, false);
        }

        return route('integrations.index', absolute: false);
    }

    private function returnToFromWorkflow(IntegrationConnectionWorkflow $workflow): string
    {
        $returnTo = $workflow->getInitialContextValue('return_to');

        return is_string($returnTo) && str_starts_with($returnTo, '/')
            ? $returnTo
            : route('integrations.index', absolute: false);
    }

    private function failCallback(
        IntegrationConnectionWorkflow $workflow,
        ExternalAuthProvider $provider,
        array $context = [],
    ): RedirectResponse {
        $workflow->apply('fail_callback', [
            'provider' => $provider->value,
            ...$context,
        ]);
        $workflow->close()->saveStore();
        session()->forget('workflow_store_id.'.IntegrationConnectionWorkflow::class);

        return redirect($this->returnToFromWorkflow($workflow))
            ->withErrors(['integration' => 'We could not complete the '.$provider->label().' connection.']);
    }

    private function normalizeEmail(?string $email): ?string
    {
        if (! is_string($email)) {
            return null;
        }

        $email = trim(mb_strtolower($email));

        return $email !== '' ? $email : null;
    }

    private function normalizeAvatarUrl(?string $avatarUrl): ?string
    {
        if (! is_string($avatarUrl) || $avatarUrl === '') {
            return null;
        }

        return filter_var($avatarUrl, FILTER_VALIDATE_URL) !== false ? $avatarUrl : null;
    }

    /**
     * @return list<string>
     */
    private function normalizeScopes(mixed $scopes): array
    {
        if (is_string($scopes)) {
            $scopes = preg_split('/\s+/', trim($scopes)) ?: [];
        }

        if (! is_array($scopes)) {
            return [];
        }

        return collect($scopes)
            ->map(static fn (mixed $scope): string => trim((string) $scope))
            ->filter()
            ->values()
            ->all();
    }

    private function hasAccessToken(SocialiteUser $providerUser): bool
    {
        return is_string($providerUser->token) && trim($providerUser->token) !== '';
    }
}
