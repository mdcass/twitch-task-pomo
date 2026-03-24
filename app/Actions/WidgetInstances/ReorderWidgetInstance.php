<?php

namespace App\Actions\WidgetInstances;

use App\Models\User;
use App\Models\WidgetInstance;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ReorderWidgetInstance
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function move(User $user, WidgetInstance $widgetInstance, string $direction): WidgetInstance
    {
        Gate::forUser($user)->authorize('update', $widgetInstance->canvas);

        Validator::make(['direction' => $direction], [
            'direction' => ['required', 'in:forward,backward,front,back'],
        ])->validate();

        DB::transaction(function () use ($widgetInstance, $direction): void {
            $orderedIds = $widgetInstance->canvas
                ->widgetInstances()
                ->orderBy('z_index')
                ->orderBy('id')
                ->pluck('id')
                ->values();

            $reorderedIds = $this->reorderedIds($orderedIds, $widgetInstance->id, $direction);

            $reorderedIds->values()->each(function (int $id, int $index): void {
                WidgetInstance::query()
                    ->whereKey($id)
                    ->update(['z_index' => $index]);
            });
        });

        return $widgetInstance->fresh();
    }

    /**
     * @param  Collection<int, int>  $orderedIds
     * @return Collection<int, int>
     */
    private function reorderedIds(Collection $orderedIds, int $widgetId, string $direction): Collection
    {
        $currentIndex = $orderedIds->search($widgetId);

        if (! is_int($currentIndex)) {
            return $orderedIds;
        }

        $ids = $orderedIds->values()->all();

        match ($direction) {
            'forward' => $this->swap($ids, $currentIndex, min(count($ids) - 1, $currentIndex + 1)),
            'backward' => $this->swap($ids, $currentIndex, max(0, $currentIndex - 1)),
            'front' => $this->moveTo($ids, $currentIndex, count($ids) - 1),
            'back' => $this->moveTo($ids, $currentIndex, 0),
            default => null,
        };

        return collect($ids);
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function swap(array &$ids, int $from, int $to): void
    {
        if ($from === $to) {
            return;
        }

        [$ids[$from], $ids[$to]] = [$ids[$to], $ids[$from]];
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function moveTo(array &$ids, int $from, int $to): void
    {
        if ($from === $to) {
            return;
        }

        $item = $ids[$from];
        array_splice($ids, $from, 1);
        array_splice($ids, $to, 0, [$item]);
    }
}
