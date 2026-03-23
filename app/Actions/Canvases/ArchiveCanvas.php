<?php

namespace App\Actions\Canvases;

use App\Models\Activity;
use App\Models\Canvas;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ArchiveCanvas
{
    /**
     * @throws AuthorizationException
     */
    public function archive(User $user, Canvas $canvas): void
    {
        Gate::forUser($user)->authorize('delete', $canvas);

        if ($canvas->trashed()) {
            return;
        }

        Activity::withCauser($user, fn (): ?bool => DB::transaction(fn (): ?bool => $canvas->delete()));
    }
}
