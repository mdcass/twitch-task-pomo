<?php

use App\Enums\ExternalAuthProvider;
use App\Enums\TeamType;
use App\Models\ProviderAuth;
use App\Models\User;

it('lets a verified user sign in with email and password', function (): void {
    $user = User::factory()->create([
        'email' => 'browser-login@example.test',
    ]);

    $this->visit('/login')
        ->assertPathIs('/login')
        ->fill('Email address', 'browser-login@example.test')
        ->fill('Password', 'password')
        ->keys('#password', 'Enter')
        ->assertPathIs('/dashboard')
        ->assertSee('Dashboard');

    expect($user->fresh())->not->toBeNull();
});

it('shows the invalid credential message on failed email sign in', function (): void {
    User::factory()->create([
        'email' => 'browser-invalid@example.test',
    ]);

    $this->visit('/login')
        ->fill('Email address', 'browser-invalid@example.test')
        ->fill('Password', 'wrong-password')
        ->keys('#password', 'Enter')
        ->assertPathIs('/login')
        ->assertSee('These credentials do not match our records.');
});

it('lets a new user register locally and lands in email verification', function (): void {
    $this->visit('/register')
        ->assertPathIs('/register')
        ->fill('Name', 'Browser Local User')
        ->fill('Email address', 'browser-local@example.test')
        ->fill('Password', 'password')
        ->fill('Confirm Password', 'password')
        ->check('#terms')
        ->press('Sign up')
        ->assertPathIs('/email/verify')
        ->assertSee('Verify your email address');

    $user = User::query()
        ->where('email', 'browser-local@example.test')
        ->firstOrFail()
        ->load('ownedTeams', 'currentTeam');

    expect($user->email_verified_at)->toBeNull();
    expect($user->ownedTeams)->toHaveCount(1);
    expect($user->ownedTeams->first()->type)->toBe(TeamType::Streamer);
    expect($user->currentTeam->is($user->ownedTeams->first()))->toBeTrue();
});

it('completes first-time Twitch signup on localhost', function (): void {
    $this->visit(route('testing.oauth.scenario', [
        'provider' => ExternalAuthProvider::Twitch->value,
        'scenario' => 'first-time-signup',
        'next' => '/register',
    ], false))
        ->assertPathIs('/register')
        ->check('#social_terms')
        ->press('Sign up with Twitch')
        ->assertPathIs('/email/verify')
        ->assertSee('Verify your email address');

    $user = User::query()
        ->where('email', 'fresh-streamer@example.test')
        ->firstOrFail()
        ->load('ownedTeams', 'currentTeam', 'providerAuths');

    expect($user->ownedTeams)->toHaveCount(1);
    expect($user->ownedTeams->first()->type)->toBe(TeamType::Streamer);
    expect($user->providerAuths)->toHaveCount(1);
    expect($user->providerAuths->first()->provider)->toBe(ExternalAuthProvider::Twitch);
});

it('collects a local email when Twitch does not provide one', function (): void {
    $this->visit(route('testing.oauth.scenario', [
        'provider' => ExternalAuthProvider::Twitch->value,
        'scenario' => 'missing-email',
        'next' => '/register',
    ], false))
        ->assertPathIs('/register')
        ->check('#social_terms')
        ->press('Sign up with Twitch')
        ->assertPathIs('/register/social-email')
        ->assertSee('Finish Sign Up')
        ->assertSee('Mystery Streamer')
        ->fill('Email address', 'missing-email-browser@example.test')
        ->press('Continue')
        ->assertPathIs('/email/verify')
        ->assertSee('Verify your email address');

    $user = User::query()
        ->where('email', 'missing-email-browser@example.test')
        ->firstOrFail()
        ->load('ownedTeams', 'providerAuths');

    expect($user->ownedTeams->first()->type)->toBe(TeamType::Streamer);
    expect($user->providerAuths)->toHaveCount(1);
    expect($user->providerAuths->first()->provider_user_id)->toBe('testing-twitch-missing-email');
});

it('shows the existing account handoff when Twitch matches a local email', function (): void {
    User::factory()->create([
        'email' => 'existing-social@example.test',
    ]);

    $this->visit(route('testing.oauth.scenario', [
        'provider' => ExternalAuthProvider::Twitch->value,
        'scenario' => 'existing-email',
        'next' => '/register',
    ], false))
        ->assertPathIs('/register')
        ->check('#social_terms')
        ->press('Sign up with Twitch')
        ->assertPathIs('/register/social-email')
        ->assertSee('Account Match Found')
        ->assertSee('existing-social@example.test')
        ->assertSee('already belongs to an existing account here.');

    expect(ProviderAuth::query()->count())->toBe(0);
});

it('logs in through an existing linked Twitch identity', function (): void {
    $user = User::factory()->create([
        'email' => 'linked-user@example.test',
    ]);

    $providerAuth = ProviderAuth::factory()->for($user)->create([
        'provider' => ExternalAuthProvider::Twitch,
        'provider_user_id' => 'testing-twitch-linked-user',
        'provider_email' => 'before-linked@example.test',
        'avatar_url' => 'https://cdn.example.test/avatars/before-linked.png',
        'last_used_at' => now()->subDay(),
    ]);

    $this->visit(route('testing.oauth.scenario', [
        'provider' => ExternalAuthProvider::Twitch->value,
        'scenario' => 'existing-linked-login',
        'next' => '/login',
    ], false))
        ->assertPathIs('/login')
        ->press('Sign in with Twitch')
        ->assertPathIs('/dashboard')
        ->assertSee('Dashboard');

    $providerAuth->refresh();

    expect($providerAuth->provider_email)->toBe('linked-streamer@example.test');
    expect($providerAuth->avatar_url)->toBe('https://cdn.example.test/avatars/linked-streamer.png');
    expect($providerAuth->last_used_at?->isAfter(now()->subMinute()))->toBeTrue();
});

it('supports Discord signup through the localhost browser harness', function (): void {
    $this->visit(route('testing.oauth.scenario', [
        'provider' => ExternalAuthProvider::Discord->value,
        'scenario' => 'first-time-signup',
        'next' => '/register',
    ], false))
        ->assertPathIs('/register')
        ->check('#social_terms')
        ->press('Sign up with Discord')
        ->assertPathIs('/email/verify')
        ->assertSee('Verify your email address');

    $user = User::query()
        ->where('email', 'discord-creator@example.test')
        ->firstOrFail()
        ->load('providerAuths');

    expect($user->providerAuths)->toHaveCount(1);
    expect($user->providerAuths->first()->provider)->toBe(ExternalAuthProvider::Discord);
});
