@php
    $stageSnapshot = $this->stageSnapshot;
    $composerCopy = [
        'previewUnavailable' => __('Preview unavailable for this widget.'),
        'previewFailureFallback' => __('This widget could not be previewed in the editor.'),
        'runtimeStates' => [
            'idle' => ['label' => __('Idle'), 'message' => null],
            'loading' => [
                'label' => __('Loading'),
                'message' => __('Editor preview is loading in this browser session.'),
            ],
            'loaded' => ['label' => __('Loaded'), 'message' => null],
            'timeout' => [
                'label' => __('Slow'),
                'message' => __('Editor preview is taking longer than expected in this browser session.'),
            ],
            'error' => [
                'label' => __('Error'),
                'message' => __('Editor preview could not be confirmed in this browser session.'),
            ],
        ],
    ];
@endphp

<div class="row g-4" data-composer-editor data-can-edit="{{ $this->can_edit ? 'true' : 'false' }}"
    data-selected-widget-id="{{ $selectedWidgetId ?? '' }}" data-delete-modal-id="canvas-widget-delete-modal"
    x-data="{
        controller: null,
        init() {
            this.controller = window.__canvasComposerCreate?.($el, $wire) ?? null;
            this.controller?.init();
        },
        destroy() { this.controller?.destroy(); }
    }">
    <div class="col-xl-9">
        <div class="card border border-translucent shadow-sm h-100">
            <div class="card-header d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div>
                    <h2 class="h5 mb-1">{{ __('Workspace') }}</h2>
                    <p class="text-body-secondary mb-0">
                        {{ __('Arrange widgets on a live canvas preview that matches your configured canvas dimensions. Changes persist when you finish dragging or resizing.') }}
                    </p>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    @if ($this->can_edit)
                        <button type="button" class="btn btn-sm btn-phoenix-secondary" data-composer-history="undo"
                            disabled>
                            <span class="fas fa-rotate-left me-2" aria-hidden="true"></span>
                            {{ __('Undo') }}
                        </button>
                        <button type="button" class="btn btn-sm btn-phoenix-secondary" data-composer-history="redo"
                            disabled>
                            <span class="fas fa-rotate-right me-2" aria-hidden="true"></span>
                            {{ __('Redo') }}
                        </button>
                    @endif
                    <span class="badge badge-phoenix badge-phoenix-secondary" data-composer-fit-badge>
                        {{ __('Fit (--%)') }}
                    </span>
                    <span class="badge badge-phoenix badge-phoenix-primary">
                        {{ __('Canvas :width x :height', ['width' => $this->canvas->width, 'height' => $this->canvas->height]) }}
                    </span>
                </div>
            </div>

            <div class="card-body">
                <div class="alert alert-subtle-warning border border-warning-subtle d-md-none" role="alert">
                    <span class="fas fa-mobile-screen-button me-2" aria-hidden="true"></span>
                    {{ __('Layout stays consistent on smaller screens, but spatial editing is desktop and tablet first.') }}
                </div>

                <div class="composer-shortcuts small text-body-secondary mb-3 d-none d-md-flex align-items-center justify-content-between flex-wrap gap-2"
                    data-composer-shortcuts>
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <span class="composer-shortcuts__pill" data-composer-shortcut="resize">
                            {{ __('Resize') }}
                        </span>
                        <span class="composer-shortcuts__pill" data-composer-shortcut="crop">
                            {{ __('Crop: Alt / Option + drag') }}
                        </span>
                        <span class="composer-shortcuts__pill" data-composer-shortcut="stretch">
                            {{ __('Stretch: Shift + drag') }}
                        </span>
                        <span class="composer-shortcuts__pill" data-composer-shortcut="source">
                            {{ __('Source: Ctrl / Cmd + drag') }}
                        </span>
                    </div>

                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <button type="button" class="btn btn-sm btn-phoenix-secondary" data-composer-reset="crop"
                            data-widget-id="{{ $this->selected_widget?->id ?? 0 }}" @disabled(!$this->selected_widget || !$this->can_edit)>
                            {{ __('Reset Crop') }}
                        </button>
                        <button type="button" class="btn btn-sm btn-phoenix-secondary" data-composer-reset="source"
                            data-widget-id="{{ $this->selected_widget?->id ?? 0 }}" @disabled(!$this->selected_widget || !$this->can_edit)>
                            {{ __('Reset Source') }}
                        </button>
                        <button type="button" class="btn btn-sm btn-phoenix-secondary" data-composer-reset="aspect"
                            data-widget-id="{{ $this->selected_widget?->id ?? 0 }}" @disabled(!$this->selected_widget || !$this->can_edit)>
                            {{ __('Reset Aspect') }}
                        </button>
                    </div>
                </div>

                <div class="composer-viewport" data-composer-viewport>
                    <div class="composer-viewport__content" data-composer-viewport-content>
                        <div class="composer-stage-camera" data-composer-stage-camera>
                            <div wire:ignore
                                class="composer-surface @if (!$this->can_edit) is-readonly @endif"
                                data-composer-stage
                                style="width: {{ max(1, $this->canvas->width) }}px; height: {{ max(1, $this->canvas->height) }}px;">
                                <div class="composer-moveable-layer" data-composer-moveable-layer wire:ignore></div>
                            </div>
                        </div>
                    </div>
                </div>

                <script type="application/json" data-composer-snapshot>@json($stageSnapshot)</script>
                <script type="application/json" data-composer-copy>@json($composerCopy)</script>
                <template data-composer-widget-template>
                    <article class="composer-widget" data-widget-id="">
                        <div class="composer-widget__preview" data-widget-preview></div>
                    </article>
                </template>
                <template data-composer-empty-state-template>
                    <div class="composer-empty-state" data-composer-empty-state>
                        <div class="icon-wrapper icon-wrapper-lg bg-primary-subtle text-primary rounded-circle mb-3">
                            <span class="fas fa-plus" aria-hidden="true"></span>
                        </div>
                        <h3 class="h4 mb-2">{{ __('Start this canvas with your first widget') }}</h3>
                        <p class="text-body-secondary mb-0">
                            {{ __('Add a built-in placeholder or an HTTPS iframe widget from the page toolbar.') }}
                        </p>
                    </div>
                </template>
                <template data-composer-placeholder-template>
                    <div class="composer-widget__placeholder">
                        <div class="mb-3">
                            <span class="fas fa-layer-group fs-3 text-primary" aria-hidden="true"></span>
                        </div>
                        <h3 class="h6 mb-2" data-placeholder-name></h3>
                        <p class="text-body-secondary small mb-3" data-placeholder-message></p>
                        <div class="d-flex flex-wrap justify-content-center gap-2 small text-body-secondary">
                            <span data-placeholder-size></span>
                            <span data-placeholder-position></span>
                        </div>
                    </div>
                </template>
                <template data-composer-runtime-message-template>
                    <div class="composer-widget__runtime-message" data-widget-runtime-preview-container>
                        <div class="alert alert-subtle-warning border border-warning-subtle mb-0 small d-none"
                            data-widget-runtime-preview-message>
                            <div class="fw-semibold mb-1">{{ __('Editor preview') }}</div>
                            <div data-widget-runtime-preview-copy></div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <div class="col-xl-3">
        <div class="d-flex flex-column gap-4">
            <div class="card border border-translucent shadow-sm">
                <div class="card-body d-flex flex-column gap-3">
                    <div class="d-flex align-items-center justify-content-between gap-3">
                        <div>
                            <div class="small text-uppercase fw-semibold text-body-tertiary mb-1">{{ __('Widgets') }}
                            </div>
                            <h3 class="h5 mb-0">{{ __('Layers') }}</h3>
                        </div>
                    </div>

                    <div class="composer-layer-list">
                        @forelse ($this->widgets as $widget)
                            <div class="composer-layer-item @if ($selectedWidgetId === $widget->id) is-selected @endif"
                                wire:key="composer-layer-{{ $widget->id }}">
                                <button type="button" class="composer-layer-item__main"
                                    wire:click="selectWidget({{ $widget->id }})"
                                    data-widget-layer-select="{{ $widget->id }}">
                                    <span class="fw-semibold d-block text-start">{{ $widget->displayName() }}</span>
                                    <span class="small text-body-secondary d-block text-start">
                                        {{ $widget->source_kind->label() }}
                                        @if ($widget->type)
                                            · {{ $widget->type->label() }}
                                        @endif
                                        @unless ($widget->is_visible)
                                            · {{ __('Hidden') }}
                                        @endunless
                                    </span>
                                </button>

                                @if ($this->can_edit)
                                    <div class="d-flex align-items-center gap-1">
                                        <button type="button" class="btn btn-sm btn-phoenix-secondary"
                                            data-composer-layer-action="visibility"
                                            data-widget-id="{{ $widget->id }}"
                                            title="{{ $widget->is_visible ? __('Hide widget') : __('Show widget') }}">
                                            <span class="fas {{ $widget->is_visible ? 'fa-eye' : 'fa-eye-slash' }}"
                                                aria-hidden="true"></span>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-phoenix-secondary"
                                            data-composer-layer-action="reorder" data-widget-id="{{ $widget->id }}"
                                            data-direction="backward" title="{{ __('Send backward') }}">
                                            <span class="fas fa-arrow-down" aria-hidden="true"></span>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-phoenix-secondary"
                                            data-composer-layer-action="reorder" data-widget-id="{{ $widget->id }}"
                                            data-direction="forward" title="{{ __('Bring forward') }}">
                                            <span class="fas fa-arrow-up" aria-hidden="true"></span>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-phoenix-secondary text-danger"
                                            data-composer-layer-action="delete" data-widget-id="{{ $widget->id }}"
                                            title="{{ __('Delete widget') }}">
                                            <span class="fas fa-trash" aria-hidden="true"></span>
                                        </button>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="text-body-secondary small">{{ __('No widgets added yet.') }}</div>
                        @endforelse
                    </div>
                </div>
            </div>

            @if ($this->selected_widget)
                <div class="card border border-translucent shadow-sm">
                    <div class="card-body d-flex flex-column gap-3">
                        <div>
                            <div class="small text-uppercase fw-semibold text-body-tertiary mb-1">
                                {{ __('Selected widget') }}</div>
                            <h3 class="h5 mb-0">{{ $this->selected_widget->displayName() }}</h3>
                        </div>

                        <dl class="row gy-2 mb-0">
                            <dt class="col-5 text-body-secondary">{{ __('Source') }}</dt>
                            <dd class="col-7 mb-0">{{ $this->selected_widget->source_kind->label() }}</dd>

                            <dt class="col-5 text-body-secondary">{{ __('Position') }}</dt>
                            <dd class="col-7 mb-0">
                                {{ $this->selected_widget->position_x }}, {{ $this->selected_widget->position_y }}
                            </dd>

                            <dt class="col-5 text-body-secondary">{{ __('Size') }}</dt>
                            <dd class="col-7 mb-0">
                                {{ $this->selected_widget->width }} x {{ $this->selected_widget->height }}
                            </dd>

                            <dt class="col-5 text-body-secondary">{{ __('Content') }}</dt>
                            <dd class="col-7 mb-0">
                                {{ $this->selected_widget->content_width }} x
                                {{ $this->selected_widget->content_height }}
                            </dd>

                            <dt class="col-5 text-body-secondary">{{ __('Crop') }}</dt>
                            <dd class="col-7 mb-0">
                                {{ __('T: :top / R: :right / B: :bottom / L: :left', [
                                    'top' => $this->selected_widget->crop_top,
                                    'right' => $this->selected_widget->crop_right,
                                    'bottom' => $this->selected_widget->crop_bottom,
                                    'left' => $this->selected_widget->crop_left,
                                ]) }}
                            </dd>

                            <dt class="col-5 text-body-secondary">{{ __('Preflight') }}</dt>
                            <dd class="col-7 mb-0">{{ $this->selected_widget->preview_status->label() }}</dd>

                            <dt class="col-5 text-body-secondary">{{ __('Session') }}</dt>
                            <dd class="col-7 mb-0" data-composer-runtime-session>
                                <span data-composer-runtime-label>{{ __('Idle') }}</span>
                                <div class="small text-body-secondary mt-1 d-none" data-composer-runtime-message></div>
                            </dd>

                            @if ($this->selected_widget->embed_url)
                                <dt class="col-5 text-body-secondary">{{ __('URL') }}</dt>
                                <dd class="col-7 mb-0 small text-break">{{ $this->selected_widget->embed_url }}</dd>
                            @endif
                        </dl>

                        @if ($this->can_edit)
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-sm btn-phoenix-secondary"
                                    data-composer-selected-action="reorder"
                                    data-widget-id="{{ $this->selected_widget->id }}" data-direction="back">
                                    {{ __('Send to back') }}
                                </button>
                                <button type="button" class="btn btn-sm btn-phoenix-secondary"
                                    data-composer-selected-action="reorder"
                                    data-widget-id="{{ $this->selected_widget->id }}" data-direction="front">
                                    {{ __('Bring to front') }}
                                </button>
                                <button type="button" class="btn btn-sm btn-danger"
                                    data-composer-selected-action="delete"
                                    data-widget-id="{{ $this->selected_widget->id }}">
                                    {{ __('Delete widget') }}
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <div class="card border border-translucent shadow-sm">
                <div class="card-body d-flex flex-column gap-3">
                    <div class="d-flex align-items-center justify-content-between gap-3">
                        <div>
                            <div class="small text-uppercase fw-semibold text-body-tertiary mb-1">{{ __('Canvas') }}
                            </div>
                            <h3 class="h5 mb-0">{{ $this->canvas->name }}</h3>
                        </div>
                    </div>

                    <div class="small text-body-secondary">
                        {{ __('Created by :name', ['name' => $this->canvas->createdByUser?->name ?? __('Unknown')]) }}
                    </div>

                    <dl class="row gy-2 mb-0">
                        <dt class="col-5 text-body-secondary">{{ __('Size') }}</dt>
                        <dd class="col-7 mb-0">{{ $this->canvas->width }} x {{ $this->canvas->height }}</dd>

                        <dt class="col-5 text-body-secondary">{{ __('Widgets') }}</dt>
                        <dd class="col-7 mb-0">{{ $this->widgets->count() }}</dd>

                        <dt class="col-5 text-body-secondary">{{ __('Updated') }}</dt>
                        <dd class="col-7 mb-0">{{ $this->canvas->updated_at->format('j M Y, H:i') }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>
