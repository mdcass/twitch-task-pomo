<?php

namespace App\Actions\Teams;

use App\Enums\TeamType;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;

class CreateOwnedTeam
{
    /**
     * @throws ValidationException
     */
    public function create(User $owner, TeamType $type, ?string $name = null): Team
    {
        Validator::make([
            'type' => $type->value,
            'name' => $name,
        ], [
            'type' => ['required', new Enum(TeamType::class)],
            'name' => ['nullable', 'string', 'max:255'],
        ])->validate();

        $team = $owner->ownedTeams()->create([
            'name' => $name ?: $this->defaultName($owner, $type),
            'type' => $type,
        ]);

        $owner->switchTeam($team);

        return $team;
    }

    protected function defaultName(User $owner, TeamType $type): string
    {
        $firstName = Str::of($owner->name)->trim()->explode(' ')->filter()->first() ?: 'New';

        return sprintf("%s's %s Profile", $firstName, $type->label());
    }
}
