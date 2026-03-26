<?php

namespace App\Livewire\Integrations;

use App\Enums\ExternalAuthProvider;
use App\Enums\Models\WidgetType;
use App\Models\Team;
use App\Services\Integrations\IntegrationConnectionService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Component;
use ValueError;

class IntegrationIndex extends Component
{
    use AuthorizesRequests;

    public function mount(): void
    {
        $this->authorize('view', $this->resolveCurrentTeam());
    }

    public function disconnect(string $provider, IntegrationConnectionService $integrationConnections): void
    {
        try {
            $providerEnum = ExternalAuthProvider::from($provider);
        } catch (ValueError) {
            abort(404);
        }

        abort_unless(in_array($providerEnum, [ExternalAuthProvider::Twitch, ExternalAuthProvider::Spotify], true), 404);

        $integrationConnections->disconnect(auth()->user(), $providerEnum);
        session()->flash('status', $providerEnum->label().' disconnected.');
    }

    #[Computed]
    public function canManage(): bool
    {
        return auth()->user()?->can('manageIntegrations', $this->resolveCurrentTeam()) ?? false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function providers(): array
    {
        $team = $this->resolveCurrentTeam();

        return collect([ExternalAuthProvider::Twitch, ExternalAuthProvider::Spotify])
            ->map(function (ExternalAuthProvider $provider) use ($team): array {
                $dependentTypes = collect(WidgetType::cases())
                    ->filter(static fn (WidgetType $type): bool => $type->definition()->requiredProvider() === $provider)
                    ->map(static fn (WidgetType $type): string => $type->value)
                    ->values()
                    ->all();

                return [
                    'provider' => $provider,
                    'auth' => $team->currentProviderAuth($provider),
                    'usage_count' => $team->widgets()->whereIn('type', $dependentTypes)->count(),
                    'widgets' => $team->widgets()->whereIn('type', $dependentTypes)->latest('updated_at')->get(),
                ];
            })
            ->all();
    }

    public function render(): View
    {
        return view('livewire.integrations.integration-index');
    }

    private function resolveCurrentTeam(): Team
    {
        $team = auth()->user()?->currentTeam;
        abort_unless($team instanceof Team, 403);

        return $team;
    }
}
