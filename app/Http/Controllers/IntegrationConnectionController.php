<?php

namespace App\Http\Controllers;

use App\Enums\ExternalAuthProvider;
use App\Services\Integrations\IntegrationConnectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class IntegrationConnectionController extends Controller
{
    public function __construct(
        private readonly IntegrationConnectionService $integrationConnections,
    ) {}

    public function redirect(Request $request, ExternalAuthProvider $provider): RedirectResponse
    {
        abort_unless(in_array($provider, [ExternalAuthProvider::Twitch, ExternalAuthProvider::Spotify], true), 404);

        $team = $request->user()?->currentTeam;
        abort_unless($team !== null, 403);

        $widget = null;
        $widgetId = $request->integer('widget');

        if ($widgetId > 0) {
            $widget = $team->widgets()->findOrFail($widgetId);
        }

        return $this->integrationConnections->redirect($request, $provider, $widget);
    }

    public function callback(Request $request, ExternalAuthProvider $provider): RedirectResponse
    {
        abort_unless(in_array($provider, [ExternalAuthProvider::Twitch, ExternalAuthProvider::Spotify], true), 404);

        return $this->integrationConnections->callback($request, $provider);
    }
}
