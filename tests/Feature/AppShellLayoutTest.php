<?php

namespace Tests\Feature;

use App\Enums\ExternalAuthProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Laravel\Jetstream\Features;
use Tests\TestCase;

class AppShellLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get('/_test/shell/{layout}', fn (string $layout) => Blade::render(<<<'BLADE'
                <x-app-layout :layout="$layout">
                    <x-slot name="header">
                        <div>
                            <h1 class="h2 mb-0">Shell Preview</h1>
                        </div>
                    </x-slot>

                    <p>Preview content</p>
                </x-app-layout>
                BLADE, ['layout' => $layout]));
    }

    public function test_existing_authenticated_pages_use_the_vertical_layout_by_default(): void
    {
        $user = $this->verifiedUserWithCurrentTeam();

        $this->actingAs($user)
            ->get(route('dashboard', absolute: false))
            ->assertOk()
            ->assertSee('data-shell-layout="vertical"', false);

        $this->actingAs($user)
            ->get(route('profile.show', absolute: false))
            ->assertOk()
            ->assertSee('data-shell-layout="vertical"', false);
    }

    public function test_each_supported_layout_variant_renders_its_shell_markers(): void
    {
        $user = $this->verifiedUserWithCurrentTeam();

        $variants = [
            'vertical' => ['default', 'default'],
            'horizontal' => ['horizontal', 'default'],
            'combo' => ['combo', 'default'],
            'dual-nav' => ['dual', 'default'],
            'topnav-slim' => ['default', 'slim'],
        ];

        foreach ($variants as $layout => [$navigationType, $shape]) {
            $this->actingAs($user)
                ->get('/_test/shell/'.$layout)
                ->assertOk()
                ->assertSee('data-shell-layout="'.$layout.'"', false)
                ->assertSee('data-navigation-type="'.$navigationType.'"', false)
                ->assertSee('data-navbar-horizontal-shape="'.$shape.'"', false);
        }
    }

    public function test_team_management_actions_are_not_rendered_in_the_shell(): void
    {
        $user = $this->verifiedUserWithCurrentTeam();

        $this->actingAs($user)
            ->get('/_test/shell/vertical')
            ->assertOk()
            ->assertDontSee('Team Settings')
            ->assertDontSee('Create Team')
            ->assertDontSee('Create New Team')
            ->assertDontSee('Switch Teams');
    }

    public function test_api_tokens_are_hidden_when_the_feature_is_disabled(): void
    {
        $user = $this->verifiedUserWithCurrentTeam();

        $this->actingAs($user)
            ->get('/_test/shell/vertical')
            ->assertOk()
            ->assertDontSee('API Tokens');
    }

    public function test_api_tokens_render_when_the_feature_is_enabled(): void
    {
        $user = $this->verifiedUserWithCurrentTeam();

        config()->set('jetstream.features', [
            Features::api(),
            Features::accountDeletion(),
        ]);

        Route::middleware('web')->get('/user/api-tokens', fn () => 'API Tokens')->name('api-tokens.index');
        Route::getRoutes()->refreshNameLookups();

        $this->actingAs($user)
            ->get('/_test/shell/vertical')
            ->assertOk()
            ->assertSee('API Tokens');
    }

    public function test_dashboard_marks_the_dashboard_navigation_item_as_active(): void
    {
        $user = $this->verifiedUserWithCurrentTeam();

        $this->actingAs($user)
            ->get(route('dashboard', absolute: false))
            ->assertOk()
            ->assertSee('class="nav-link label-1 active" href="/dashboard"', false)
            ->assertDontSee('class="nav-link label-1 active" href="/user/profile"', false);
    }

    public function test_theme_switcher_remains_present_without_search_or_notifications(): void
    {
        $user = $this->verifiedUserWithCurrentTeam();

        $this->actingAs($user)
            ->get(route('dashboard', absolute: false))
            ->assertOk()
            ->assertSee('data-theme-control="phoenixTheme"', false)
            ->assertDontSee('navbar-top-search-box', false)
            ->assertDontSee('navbarDropdownNotification', false);
    }

    public function test_shell_uses_provider_avatar_when_profile_photo_is_unavailable(): void
    {
        $user = $this->verifiedUserWithCurrentTeam();
        $user->providerAuths()->create([
            'provider' => ExternalAuthProvider::Twitch,
            'provider_user_id' => 'twitch-provider-1',
            'avatar_url' => 'https://cdn.example.test/avatars/provider-fallback.png',
            'last_used_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/_test/shell/vertical')
            ->assertOk()
            ->assertSee('src="https://cdn.example.test/avatars/provider-fallback.png"', false)
            ->assertSee("onerror=\"this.classList.add('d-none'); this.nextElementSibling.classList.remove('d-none');\"", false);
    }

    public function test_shell_prefers_profile_photo_over_provider_avatar(): void
    {
        config()->set('jetstream.features', [
            Features::profilePhotos(),
            Features::accountDeletion(),
        ]);

        $user = $this->verifiedUserWithCurrentTeam()->forceFill([
            'profile_photo_path' => 'profile-photos/preferred-avatar.jpg',
        ]);
        $user->save();
        $user->providerAuths()->create([
            'provider' => ExternalAuthProvider::Discord,
            'provider_user_id' => 'discord-provider-1',
            'avatar_url' => 'https://cdn.example.test/avatars/provider-fallback.png',
            'last_used_at' => now(),
        ]);

        $this->actingAs($user->fresh())
            ->get('/_test/shell/vertical')
            ->assertOk()
            ->assertSee('preferred-avatar.jpg', false)
            ->assertDontSee('src="https://cdn.example.test/avatars/provider-fallback.png"', false);
    }

    public function test_shell_uses_most_recent_provider_avatar_when_multiple_are_available(): void
    {
        $user = $this->verifiedUserWithCurrentTeam();
        $user->providerAuths()->create([
            'provider' => ExternalAuthProvider::Twitch,
            'provider_user_id' => 'older-provider',
            'avatar_url' => 'https://cdn.example.test/avatars/older-provider.png',
            'last_used_at' => now()->subHour(),
        ]);
        $user->providerAuths()->create([
            'provider' => ExternalAuthProvider::Discord,
            'provider_user_id' => 'newer-provider',
            'avatar_url' => 'https://cdn.example.test/avatars/newer-provider.png',
            'last_used_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/_test/shell/vertical')
            ->assertOk()
            ->assertSee('src="https://cdn.example.test/avatars/newer-provider.png"', false)
            ->assertDontSee('src="https://cdn.example.test/avatars/older-provider.png"', false);
    }

    public function test_shell_ignores_revoked_and_null_avatar_provider_auths(): void
    {
        $user = User::factory()->withStreamerTeam()->create([
            'name' => 'Taylor Streamer',
        ])->fresh();

        $user->providerAuths()->create([
            'provider' => ExternalAuthProvider::Twitch,
            'provider_user_id' => 'null-avatar-provider',
            'avatar_url' => null,
            'last_used_at' => now()->subMinutes(10),
        ]);
        $revoked = $user->providerAuths()->create([
            'provider' => ExternalAuthProvider::Discord,
            'provider_user_id' => 'revoked-provider',
            'avatar_url' => 'https://cdn.example.test/avatars/revoked-provider.png',
            'last_used_at' => now(),
        ]);
        $revoked->delete();

        $this->actingAs($user)
            ->get('/_test/shell/vertical')
            ->assertOk()
            ->assertDontSee('src="https://cdn.example.test/avatars/revoked-provider.png"', false)
            ->assertSee('<span>TS</span>', false);
    }

    protected function verifiedUserWithCurrentTeam(): User
    {
        return User::factory()->withStreamerTeam()->create()->fresh();
    }
}
