<?php

namespace Database\Factories;

use App\Enums\ExternalAuthProvider;
use App\Models\ProviderAuth;
use App\Models\Stream;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stream>
 */
class StreamFactory extends Factory
{
    protected $model = Stream::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'provider_auth_id' => ProviderAuth::factory()->state(fn () => [
                'provider' => ExternalAuthProvider::Twitch,
            ]),
            'provider' => ExternalAuthProvider::Twitch,
            'provider_channel_id' => fake()->unique()->numerify('channel-########'),
            'channel_login' => fake()->unique()->userName(),
            'display_name' => fake()->name(),
            'metadata' => [],
        ];
    }
}
