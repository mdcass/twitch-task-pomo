<?php

namespace Database\Factories;

use App\Enums\ExternalAuthProvider;
use App\Models\ProviderAuth;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderAuth>
 */
class ProviderAuthFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => ExternalAuthProvider::Twitch,
            'provider_user_id' => fake()->unique()->numerify('provider-#######'),
            'provider_email' => fake()->optional()->safeEmail(),
            'avatar_url' => 'https://cdn.example.test/avatars/'.fake()->uuid().'.png',
            'access_token' => fake()->sha256(),
            'refresh_token' => fake()->sha256(),
            'token_expires_at' => now()->addHour(),
            'scopes' => ['user:read:email'],
            'profile' => [
                'display_name' => fake()->userName(),
            ],
            'last_used_at' => now(),
            'deleted_at' => null,
        ];
    }

    public function discord(): static
    {
        return $this->state(fn () => [
            'provider' => ExternalAuthProvider::Discord,
        ]);
    }

    public function spotify(): static
    {
        return $this->state(fn () => [
            'provider' => ExternalAuthProvider::Spotify,
            'provider_user_id' => fake()->unique()->numerify('spotify-#######'),
            'scopes' => ['user-read-email', 'user-read-currently-playing'],
            'profile' => [
                'display_name' => fake()->words(2, true),
            ],
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => [
            'deleted_at' => now(),
        ]);
    }
}
