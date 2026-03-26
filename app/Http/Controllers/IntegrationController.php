<?php

namespace App\Http\Controllers;

use App\Actions\Integrations\IntegrationConnectionService;
use App\Enums\ExternalAuthProvider;
use App\Models\Widget;
use App\Support\Integrations\TeamProviderAuthResolver;
use App\Support\Widgets\WidgetDefinitionRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class IntegrationController extends Controller
{
    public function __construct(
        private readonly IntegrationConnectionService $integrationConnections,
        private readonly TeamProviderAuthResolver $providerAuthResolver,
        private readonly WidgetDefinitionRegistry $definitions,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->currentTeam !== null, 403);

        $team = $request->user()->currentTeam;
        $providers = collect([ExternalAuthProvider::Twitch, ExternalAuthProvider::Spotify])
            ->map(function (ExternalAuthProvider $provider) use ($team): array {
                $dependentTypes = collect($this->definitions->all())
                    ->filter(fn ($definition) => $definition->requiredProvider() === $provider)
                    ->map(fn ($definition) => $definition->type()->value)
                    ->all();

                return [
                    'provider' => $provider,
                    'auth' => $this->providerAuthResolver->current($team, $provider),
                    'usage_count' => $team->widgets()->whereIn('type', $dependentTypes)->count(),
                    'widgets' => $team->widgets()->whereIn('type', $dependentTypes)->latest('updated_at')->get(),
                ];
            })
            ->all();

        return view('integrations.index', [
            'providers' => $providers,
            'canManage' => $request->user()->ownsTeam($team),
        ]);
    }

    public function redirect(Request $request, ExternalAuthProvider $provider): RedirectResponse
    {
        abort_unless(in_array($provider, [ExternalAuthProvider::Twitch, ExternalAuthProvider::Spotify], true), 404);

        $widget = null;
        $widgetId = $request->integer('widget');

        if ($widgetId > 0) {
            $widget = Widget::query()->findOrFail($widgetId);
        }

        return $this->integrationConnections->redirect($request, $provider, $widget);
    }

    public function callback(Request $request, ExternalAuthProvider $provider): RedirectResponse
    {
        abort_unless(in_array($provider, [ExternalAuthProvider::Twitch, ExternalAuthProvider::Spotify], true), 404);

        return $this->integrationConnections->callback($request, $provider);
    }

    public function disconnect(Request $request, ExternalAuthProvider $provider): RedirectResponse
    {
        abort_unless(in_array($provider, [ExternalAuthProvider::Twitch, ExternalAuthProvider::Spotify], true), 404);

        $this->integrationConnections->disconnect($request->user(), $provider);

        return redirect(route('integrations.index', absolute: false))
            ->with('status', $provider->label().' disconnected.');
    }
}
