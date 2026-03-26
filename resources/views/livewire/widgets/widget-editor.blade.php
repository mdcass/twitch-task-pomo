@php
    $widget = $this->widget;
    $definition = $this->definition;
    $provider = $definition->requiredProvider();
@endphp

<div class="row g-4">
    <div class="col-xl-7">
        <div class="card border border-translucent shadow-sm h-100">
            <div class="card-header d-flex justify-content-between align-items-center gap-3">
                <div>
                    <div class="small text-uppercase fw-semibold text-body-tertiary mb-1">{{ __('Configuration') }}</div>
                    <h2 class="h5 mb-0">{{ $widget->displayName() }}</h2>
                </div>

                @if ($this->canManage)
                    <button type="button" class="btn btn-primary" wire:click="save">{{ __('Save changes') }}</button>
                @endif
            </div>

            <div class="card-body d-flex flex-column gap-3">
                @if (session('status'))
                    <div class="alert alert-subtle-success border border-success-subtle mb-0">
                        {{ session('status') }}
                    </div>
                @endif

                <div>
                    <label class="form-label" for="widget-name">{{ __('Name') }}</label>
                    <input id="widget-name" type="text" class="form-control" wire:model="fields.name"
                        @disabled(!$this->canManage)>
                </div>

                @include($definition->editorView(), ['fields' => $fields])

                @if ($provider)
                    <div
                        class="alert {{ $this->providerAuth ? 'alert-subtle-info border-info-subtle' : 'alert-subtle-warning border-warning-subtle' }} border mb-0">
                        <div class="fw-semibold mb-1">{{ $provider->label() }}</div>
                        <div class="small text-body-secondary">
                            @if ($this->providerAuth)
                                {{ __('Connected as :identity', ['identity' => $this->providerAuth->provider_email ?? $this->providerAuth->provider_user_id]) }}
                            @else
                                {{ __('No active :provider connection for the team owner.', ['provider' => $provider->label()]) }}
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="d-flex flex-column gap-4">
            <div class="card border border-translucent shadow-sm">
                <div class="card-header">
                    <div class="small text-uppercase fw-semibold text-body-tertiary mb-1">{{ __('Preview') }}</div>
                    <h2 class="h5 mb-0">{{ __('Live preview') }}</h2>
                </div>
                <div class="card-body">
                    @switch($widget->type->value)
                        @case('task_list')
                            <article class="overlay-widget-card overlay-widget-card--artboard overlay-widget-card--task-list">
                                @include('widgets.shared.task-list', $this->previewData)
                            </article>
                        @break

                        @case('pomodoro')
                            <article class="overlay-widget-card overlay-widget-card--artboard overlay-widget-card--pomodoro">
                                @include('widgets.shared.pomodoro', $this->previewData)
                            </article>
                        @break

                        @case('follower_goal')
                            @include('widgets.shared.follower-goal', $this->previewData)
                        @break

                        @case('spotify_now_playing')
                            @include('widgets.shared.spotify-now-playing', $this->previewData)
                        @break
                    @endswitch
                </div>
            </div>

            <div class="card border border-translucent shadow-sm">
                <div class="card-body d-flex flex-column gap-3">
                    <dl class="row gy-2 mb-0">
                        <dt class="col-5 text-body-secondary">{{ __('Type') }}</dt>
                        <dd class="col-7 mb-0">{{ $widget->type->label() }}</dd>

                        <dt class="col-5 text-body-secondary">{{ __('Lifecycle') }}</dt>
                        <dd class="col-7 mb-0">{{ $widget->lifecycle_state->label() }}</dd>

                        <dt class="col-5 text-body-secondary">{{ __('Canvas usage') }}</dt>
                        <dd class="col-7 mb-0">{{ $widget->canvas_widgets_count ?? $widget->usageCount() }}</dd>

                        <dt class="col-5 text-body-secondary">{{ __('Standalone') }}</dt>
                        <dd class="col-7 mb-0">
                            {{ $widget->published_at ? __('Published') : __('Unpublished') }}
                        </dd>
                    </dl>

                    @if ($provider && $this->canManage)
                        <a class="btn btn-phoenix-secondary"
                            href="{{ route('integrations.redirect', ['provider' => $provider->value, 'widget' => $widget->id, 'return_to' => route('widgets.show', $widget, false)], false) }}">
                            {{ $this->providerAuth ? __('Reconnect :provider', ['provider' => $provider->label()]) : __('Connect :provider', ['provider' => $provider->label()]) }}
                        </a>
                    @endif

                    @if ($widget->lifecycle_state->value === 'pending_connection')
                        <div class="alert alert-subtle-warning border border-warning-subtle mb-0 small">
                            {{ __('This widget stays attached on canvases, but runtime rendering is blocked until the team owner reconnects its required provider.') }}
                        </div>
                    @endif

                    @if ($widget->lifecycle_state->value === 'archived')
                        <div class="alert alert-subtle-warning border border-warning-subtle mb-0 small">
                            {{ __('Archived widgets keep their canvas placements, but runtime and standalone rendering stay disabled until restored.') }}
                        </div>
                    @endif

                    @if ($this->canManage)
                        <div class="d-flex flex-wrap gap-2">
                            @if ($widget->published_at)
                                <button type="button" class="btn btn-phoenix-secondary" wire:click="regenerate">
                                    {{ __('Regenerate URL') }}
                                </button>
                                <button type="button" class="btn btn-phoenix-secondary" wire:click="unpublish">
                                    {{ __('Unpublish') }}
                                </button>
                            @else
                                <button type="button" class="btn btn-phoenix-secondary" wire:click="publish">
                                    {{ __('Publish') }}
                                </button>
                            @endif

                            @if ($widget->lifecycle_state->value === 'archived')
                                <button type="button" class="btn btn-phoenix-secondary" wire:click="restore">
                                    {{ __('Restore') }}
                                </button>
                            @else
                                <button type="button" class="btn btn-danger" wire:click="archive">
                                    {{ __('Archive') }}
                                </button>
                            @endif
                        </div>
                    @endif

                    @if ($this->publishedUrl)
                        <div>
                            <label class="form-label small" for="widget-published-url">{{ __('Published URL') }}</label>
                            <input id="widget-published-url" type="text" class="form-control"
                                value="{{ $this->publishedUrl }}" readonly>
                        </div>
                    @endif

                    @if ($widget->type->value === 'follower_goal' && $this->canManage)
                        <button type="button" class="btn btn-phoenix-secondary" wire:click="resetFollowerGoal">
                            {{ __('Reset Goal Progress') }}
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
