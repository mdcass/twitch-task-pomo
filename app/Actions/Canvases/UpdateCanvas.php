<?php

namespace App\Actions\Canvases;

use App\Actions\Canvases\Concerns\ValidatesCanvasAttributes;
use App\Models\Activity;
use App\Models\Canvas;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateCanvas
{
    use ValidatesCanvasAttributes;

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws AuthorizationException
     */
    public function update(User $user, Canvas $canvas, array $input): Canvas
    {
        Gate::forUser($user)->authorize('update', $canvas);

        $validated = $this->validateCanvasAttributes($input);
        $canvas->fill($validated);

        if (! $canvas->isDirty(['name', 'width', 'height'])) {
            return $canvas;
        }

        Activity::withCauser($user, fn (): bool => DB::transaction(fn (): bool => $canvas->save()));

        return $canvas->fresh();
    }
}
