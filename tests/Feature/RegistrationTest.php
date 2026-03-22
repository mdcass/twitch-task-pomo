<?php

namespace Tests\Feature;

use App\Enums\TeamType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Laravel\Jetstream\Jetstream;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertSee('Sign up with Twitch');
        $response->assertSee('Sign up with Discord');
        $response->assertSee('Sign Up');
        $response->assertSee('Create your account today.');
        $response->assertSee('Name');
        $response->assertSee('Email address');
        $response->assertSee('Sign in to an existing account');
    }

    public function test_registration_screen_cannot_be_rendered_if_support_is_disabled(): void
    {
        if (Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is enabled.');
        }

        $response = $this->get('/register');

        $response->assertStatus(404);
    }

    public function test_new_users_can_register(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = auth()->user()->fresh()->load('ownedTeams', 'currentTeam');

        $this->assertCount(1, $user->ownedTeams);
        $this->assertSame(TeamType::Streamer, $user->ownedTeams->first()->type);
        $this->assertTrue($user->currentTeam->is($user->ownedTeams->first()));
        $this->assertSame('Test\'s Streamer Profile', $user->currentTeam->name);
    }
}
