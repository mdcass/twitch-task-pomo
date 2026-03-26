<?php

namespace App\Http\Controllers;

use App\Actions\Widgets\CreateWidget;
use App\Actions\Widgets\SetWidgetArchivedState;
use App\Actions\Widgets\UpdateFollowerGoalState;
use App\Actions\Widgets\UpdateWidget;
use App\Actions\Widgets\UpdateWidgetPublication;
use App\Enums\Models\WidgetLifecycleState;
use App\Enums\Models\WidgetType;
use App\Models\Widget;
use App\Support\Integrations\TeamProviderAuthResolver;
use App\Support\Routing\OriginUrlGenerator;
use App\Support\Widgets\WidgetDefinitionRegistry;
use App\Support\Widgets\WidgetRenderDataFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

class WidgetController extends Controller
{
    public function __construct(
        private readonly TeamProviderAuthResolver $providerAuthResolver,
        private readonly WidgetDefinitionRegistry $definitions,
        private readonly WidgetRenderDataFactory $renderData,
        private readonly OriginUrlGenerator $originUrlGenerator,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Widget::class);

        $team = $request->user()->currentTeam;
        $search = trim((string) $request->query('search', ''));
        $type = trim((string) $request->query('type', ''));
        $lifecycle = trim((string) $request->query('lifecycle', ''));

        $widgets = $team->widgets()
            ->withCount('canvasWidgets')
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->when($type !== '', fn ($query) => $query->where('type', $type))
            ->when($lifecycle !== '', fn ($query) => $query->where('lifecycle_state', $lifecycle))
            ->latest('updated_at')
            ->get();

        return view('widgets.index', [
            'widgets' => $widgets,
            'search' => $search,
            'selectedType' => $type,
            'selectedLifecycle' => $lifecycle,
            'widgetTypes' => WidgetType::cases(),
            'lifecycleStates' => WidgetLifecycleState::cases(),
        ]);
    }

    public function store(Request $request, CreateWidget $createWidget): RedirectResponse
    {
        $this->authorize('create', Widget::class);

        $validated = $request->validate([
            'type' => ['required', new Enum(WidgetType::class)],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $widget = $createWidget->create(
            $request->user(),
            $request->user()->currentTeam,
            WidgetType::from($validated['type']),
            $validated['name'] ?? null,
        );

        return redirect(route('widgets.show', $widget->id, false))
            ->with('status', 'Widget created.');
    }

    public function show(Widget $widget): View
    {
        $this->authorize('view', $widget);

        $definition = $this->definitions->forType($widget->type);
        $requiredProvider = $definition->requiredProvider();

        return view('widgets.show', [
            'widget' => $widget->loadCount('canvasWidgets')->load('followerGoalState', 'team.owner'),
            'definition' => $definition,
            'renderData' => $this->renderData->forWidget($widget),
            'providerAuth' => $requiredProvider !== null
                ? $this->providerAuthResolver->current($widget->team, $requiredProvider)
                : null,
            'requiredProvider' => $requiredProvider,
            'publishedUrl' => $widget->isPublished()
                ? $this->originUrlGenerator->signedOverlayRoute('overlay.widgets.published', [
                    'widget' => $widget->uuid,
                    'key' => $widget->publication_key,
                ])
                : null,
        ]);
    }

    public function update(Request $request, Widget $widget, UpdateWidget $updateWidget): RedirectResponse
    {
        $updateWidget->update($request->user(), $widget, $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'config' => ['required', 'array'],
            'appearance' => ['required', 'array'],
        ]));

        return redirect(route('widgets.show', $widget->id, false))
            ->with('status', 'Widget updated.');
    }

    public function publish(Request $request, Widget $widget, UpdateWidgetPublication $publication): RedirectResponse
    {
        $publication->publish($request->user(), $widget);

        return redirect(route('widgets.show', $widget->id, false))
            ->with('status', 'Widget published.');
    }

    public function unpublish(Request $request, Widget $widget, UpdateWidgetPublication $publication): RedirectResponse
    {
        $publication->unpublish($request->user(), $widget);

        return redirect(route('widgets.show', $widget->id, false))
            ->with('status', 'Widget unpublished.');
    }

    public function rotatePublicationKey(Request $request, Widget $widget, UpdateWidgetPublication $publication): RedirectResponse
    {
        $publication->rotate($request->user(), $widget);

        return redirect(route('widgets.show', $widget->id, false))
            ->with('status', 'Widget URL regenerated.');
    }

    public function archive(Request $request, Widget $widget, SetWidgetArchivedState $archiveState): RedirectResponse
    {
        $archiveState->archive($request->user(), $widget);

        return redirect(route('widgets.show', $widget->id, false))
            ->with('status', 'Widget archived.');
    }

    public function restore(Request $request, Widget $widget, SetWidgetArchivedState $archiveState): RedirectResponse
    {
        $archiveState->restore($request->user(), $widget);

        return redirect(route('widgets.show', $widget->id, false))
            ->with('status', 'Widget restored.');
    }

    public function resetFollowerGoal(Request $request, Widget $widget, UpdateFollowerGoalState $followerGoalState): RedirectResponse
    {
        $followerGoalState->reset($request->user(), $widget);

        return redirect(route('widgets.show', $widget->id, false))
            ->with('status', 'Follower Goal reset.');
    }
}
