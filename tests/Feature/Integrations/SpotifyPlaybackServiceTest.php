<?php

namespace Tests\Feature\Integrations;

use App\Enums\ExternalAuthProvider;
use App\Models\ProviderAuth;
use App\Models\User;
use App\Services\Integrations\SpotifyPlaybackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SpotifyPlaybackServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_payload_for_team_is_cached_between_calls(): void
    {
        Cache::flush();
        Http::fake([
            'https://api.spotify.com/v1/me/player/currently-playing' => Http::response([
                'is_playing' => true,
                'item' => [
                    'name' => 'Focus Track',
                    'artists' => [
                        ['name' => 'Synthwave Artist'],
                    ],
                    'album' => [
                        'images' => [
                            ['url' => 'https://cdn.example.test/focus-track.png'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->withStreamerTeam()->create();
        $team = $user->currentTeam;

        ProviderAuth::factory()->for($user)->spotify()->create([
            'provider' => ExternalAuthProvider::Spotify,
            'token_expires_at' => now()->addMinutes(10),
        ]);

        $service = app(SpotifyPlaybackService::class);

        $firstPayload = $service->payloadForTeam($team);
        $secondPayload = $service->payloadForTeam($team);

        $this->assertSame($firstPayload, $secondPayload);
        $this->assertSame('playing', $firstPayload['status']);
        $this->assertSame('Focus Track', $firstPayload['track']['title']);
        Http::assertSentCount(1);
    }
}
