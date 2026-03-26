<?php

namespace App\Support\Integrations;

use App\Enums\ExternalAuthProvider;
use App\Models\ProviderAuth;
use App\Models\Team;
use App\Models\User;

class TeamProviderAuthResolver
{
    public function owner(Team $team): ?User
    {
        return $team->owner ?? $team->user ?? null;
    }

    public function current(Team $team, ExternalAuthProvider $provider): ?ProviderAuth
    {
        $owner = $this->owner($team);

        if (! $owner instanceof User) {
            return null;
        }

        return $owner->providerAuths()
            ->where('provider', $provider)
            ->orderByDesc('last_used_at')
            ->orderByDesc('id')
            ->first();
    }

    public function isConnected(Team $team, ExternalAuthProvider $provider): bool
    {
        return $this->current($team, $provider) instanceof ProviderAuth;
    }
}
