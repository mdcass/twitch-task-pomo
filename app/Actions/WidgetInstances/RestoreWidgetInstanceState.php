<?php

namespace App\Actions\WidgetInstances;

use App\Models\Canvas;
use App\Models\User;
use App\Models\WidgetInstance;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RestoreWidgetInstanceState
{
    public function __construct(
        private readonly UpdateWidgetGeometry $updateWidgetGeometry,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $widgets
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function restore(User $user, Canvas $canvas, array $widgets): void
    {
        Gate::forUser($user)->authorize('update', $canvas);

        $validated = Validator::make([
            'widgets' => $widgets,
        ], [
            'widgets' => ['required', 'array'],
            'widgets.*.id' => ['required', 'integer'],
            'widgets.*.position_x' => ['required', 'integer'],
            'widgets.*.position_y' => ['required', 'integer'],
            'widgets.*.width' => ['required', 'integer'],
            'widgets.*.height' => ['required', 'integer'],
            'widgets.*.content_width' => ['required', 'integer'],
            'widgets.*.content_height' => ['required', 'integer'],
            'widgets.*.crop_top' => ['required', 'integer'],
            'widgets.*.crop_right' => ['required', 'integer'],
            'widgets.*.crop_bottom' => ['required', 'integer'],
            'widgets.*.crop_left' => ['required', 'integer'],
            'widgets.*.is_visible' => ['required', 'boolean'],
        ])->validate()['widgets'];

        $currentIds = $canvas->widgetInstances()->orderBy('z_index')->orderBy('id')->pluck('id')->values()->all();
        $providedIds = array_values(array_map(
            static fn (array $widget): int => (int) $widget['id'],
            $validated,
        ));

        sort($currentIds);
        $sortedProvidedIds = $providedIds;
        sort($sortedProvidedIds);

        if ($currentIds !== $sortedProvidedIds || count($providedIds) !== count(array_unique($providedIds))) {
            throw ValidationException::withMessages([
                'widgets' => __('The widget history state no longer matches this canvas.'),
            ]);
        }

        $widgetsById = $canvas->widgetInstances()
            ->whereIn('id', $providedIds)
            ->get()
            ->keyBy('id');

        DB::transaction(function () use ($validated, $widgetsById, $canvas): void {
            foreach ($validated as $index => $widgetState) {
                /** @var WidgetInstance $widget */
                $widget = $widgetsById->get((int) $widgetState['id']);
                $geometry = $this->updateWidgetGeometry->normalizeForCanvas($canvas, Arr::only($widgetState, [
                    'position_x',
                    'position_y',
                    'width',
                    'height',
                    'content_width',
                    'content_height',
                    'crop_top',
                    'crop_right',
                    'crop_bottom',
                    'crop_left',
                ]));

                $widget->fill([
                    ...$geometry->toArray(),
                    'is_visible' => (bool) $widgetState['is_visible'],
                    'z_index' => $index,
                ]);
                $widget->save();
            }
        });
    }
}
