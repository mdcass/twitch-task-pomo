<?php

namespace App\Actions\WidgetInstances;

use App\Models\User;
use App\Models\WidgetInstance;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DeleteWidgetInstance
{
    /**
     * @throws AuthorizationException
     */
    public function delete(User $user, WidgetInstance $widgetInstance): ?int
    {
        Gate::forUser($user)->authorize('update', $widgetInstance->canvas);

        return DB::transaction(function () use ($widgetInstance): ?int {
            $orderedIds = $widgetInstance->canvas
                ->widgetInstances()
                ->orderBy('z_index')
                ->orderBy('id')
                ->pluck('id')
                ->values();

            $currentIndex = $orderedIds->search($widgetInstance->id);
            $remainingIds = $orderedIds
                ->reject(fn (int $id): bool => $id === $widgetInstance->id)
                ->values();

            $nextSelectedId = null;

            if (is_int($currentIndex) && $remainingIds->isNotEmpty()) {
                $nextSelectedId = $remainingIds->get($currentIndex)
                    ?? $remainingIds->get(max(0, $currentIndex - 1));
            }

            $widgetInstance->delete();

            $remainingIds->each(function (int $id, int $index): void {
                WidgetInstance::query()
                    ->whereKey($id)
                    ->update(['z_index' => $index]);
            });

            return is_int($nextSelectedId) ? $nextSelectedId : null;
        });
    }
}
