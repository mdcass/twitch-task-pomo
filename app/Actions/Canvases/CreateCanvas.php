<?php

namespace App\Actions\Canvases;

use App\Actions\Canvases\Concerns\ValidatesCanvasAttributes;
use App\Models\Activity;
use App\Models\Canvas;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateCanvas
{
    use ValidatesCanvasAttributes;

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws AuthorizationException
     */
    public function create(User $user, array $input): Canvas
    {
        Gate::forUser($user)->authorize('create', Canvas::class);

        $team = $user->currentTeam;

        $validated = $this->validateCanvasAttributes($input);

        $canvas = Activity::withCauser($user, fn (): Canvas => DB::transaction(fn (): Canvas => $team->canvases()->create([
            ...$validated,
            'created_by_user_id' => $user->id,
        ])));

        return $canvas;
    }
}
