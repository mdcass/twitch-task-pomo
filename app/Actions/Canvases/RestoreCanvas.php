<?php

namespace App\Actions\Canvases;

use App\Models\Activity;
use App\Models\Canvas;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RestoreCanvas
{
    /**
     * @throws AuthorizationException
     */
    public function restore(User $user, Canvas $canvas): Canvas
    {
        Gate::forUser($user)->authorize('restore', $canvas);

        if (! $canvas->trashed()) {
            return $canvas;
        }

        Activity::withCauser($user, fn (): bool => DB::transaction(fn (): bool => $canvas->restore()));

        return $canvas->fresh();
    }
}
