<?php

namespace App\Actions\WidgetInstances;

use App\Enums\Models\WidgetSourceKind;
use App\Models\Canvas;
use App\Models\User;
use App\Models\WidgetInstance;
use App\Support\Widgets\RemoteWidgetPreviewInspector;
use App\Support\Widgets\RemoteWidgetUrlGuard;
use App\Support\Widgets\WidgetGeometry;
use App\Support\Widgets\WidgetInstanceSpec;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateRemoteWidget
{
    public function __construct(
        private readonly CreateWidgetInstance $createWidgetInstance,
        private readonly RemoteWidgetPreviewInspector $previewInspector,
        private readonly RemoteWidgetUrlGuard $remoteWidgetUrlGuard,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function create(User $user, Canvas $canvas, array $input): WidgetInstance
    {
        Gate::forUser($user)->authorize('update', $canvas);

        $validated = Validator::make($input, [
            'name' => ['nullable', 'string', 'max:255'],
            'embed_url' => [
                'required',
                'string',
                'max:2048',
                'url',
                static function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || parse_url($value, PHP_URL_SCHEME) !== 'https') {
                        $fail('The embed URL must be a valid HTTPS URL.');
                    }
                },
            ],
        ], [
            'embed_url.url' => 'The embed URL must be a valid HTTPS URL.',
        ])->validate();

        $validated['embed_url'] = $this->remoteWidgetUrlGuard->assertAllowed($validated['embed_url']);
        $previewResult = $this->previewInspector->inspect($validated['embed_url']);

        return $this->createWidgetInstance->create($canvas, new WidgetInstanceSpec(
            sourceKind: WidgetSourceKind::RemoteUrl,
            geometry: $this->defaultGeometryFor($canvas),
            name: trim((string) ($validated['name'] ?? '')) ?: $this->defaultName($validated['embed_url']),
            embedUrl: $validated['embed_url'],
            previewStatus: $previewResult->status,
            previewMessage: $previewResult->message,
            previewCheckedAt: $previewResult->checkedAt,
        ));
    }

    private function defaultName(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'Remote Widget';
    }

    private function defaultGeometryFor(Canvas $canvas): WidgetGeometry
    {
        $placementIndex = (int) $canvas->widgetInstances()->max('z_index') + 1;

        return WidgetGeometry::uncropped(
            positionX: min(
                WidgetGeometry::DEFAULT_REMOTE_WIDGET_BASE_X + (($placementIndex - 1) * WidgetGeometry::DEFAULT_REMOTE_WIDGET_STEP_X),
                WidgetGeometry::DEFAULT_REMOTE_WIDGET_MAX_X,
            ),
            positionY: min(
                WidgetGeometry::DEFAULT_REMOTE_WIDGET_BASE_Y + (($placementIndex - 1) * WidgetGeometry::DEFAULT_REMOTE_WIDGET_STEP_Y),
                WidgetGeometry::DEFAULT_REMOTE_WIDGET_MAX_Y,
            ),
            width: WidgetGeometry::DEFAULT_REMOTE_WIDGET_WIDTH,
            height: WidgetGeometry::DEFAULT_REMOTE_WIDGET_HEIGHT,
            contentWidth: WidgetGeometry::DEFAULT_REMOTE_WIDGET_WIDTH,
            contentHeight: WidgetGeometry::DEFAULT_REMOTE_WIDGET_HEIGHT,
        );
    }
}
