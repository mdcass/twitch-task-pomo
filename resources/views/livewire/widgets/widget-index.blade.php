<div class="d-flex flex-column gap-4">
    @if (session('status'))
        <div class="alert alert-subtle-success border border-success-subtle mb-0" role="alert">
            {{ session('status') }}
        </div>
    @endif

    @if ($this->canCreate)
        <div class="card border border-translucent shadow-sm">
            <div class="card-body">
                <form wire:submit="create" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label" for="widget-type">{{ __('Widget type') }}</label>
                        <select id="widget-type" class="form-select @error('fields.type') is-invalid @enderror"
                            wire:model="fields.type">
                            @foreach ($widgetTypes as $widgetType)
                                <option value="{{ $widgetType->value }}">{{ $widgetType->label() }}</option>
                            @endforeach
                        </select>
                        @error('fields.type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-5">
                        <label class="form-label" for="widget-name">{{ __('Name') }}</label>
                        <input id="widget-name" type="text"
                            class="form-control @error('fields.name') is-invalid @enderror" wire:model="fields.name"
                            placeholder="{{ __('Optional custom name') }}">
                        @error('fields.name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3 d-grid">
                        <button type="submit" class="btn btn-primary">
                            <span class="fas fa-plus me-2" aria-hidden="true"></span>
                            {{ __('Create widget') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="card border border-translucent shadow-sm">
        <div class="card-body">
            <form wire:submit="$refresh" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label" for="widget-search">{{ __('Search') }}</label>
                    <input id="widget-search" type="search" class="form-control" wire:model="search"
                        placeholder="{{ __('Search widgets') }}">
                </div>

                <div class="col-md-3">
                    <label class="form-label" for="widget-filter-type">{{ __('Type') }}</label>
                    <select id="widget-filter-type" class="form-select" wire:model="type">
                        <option value="">{{ __('All types') }}</option>
                        @foreach ($widgetTypes as $widgetType)
                            <option value="{{ $widgetType->value }}">{{ $widgetType->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label" for="widget-filter-lifecycle">{{ __('Health') }}</label>
                    <select id="widget-filter-lifecycle" class="form-select" wire:model="lifecycle">
                        <option value="">{{ __('All health states') }}</option>
                        @foreach ($lifecycleStates as $state)
                            <option value="{{ $state->value }}">{{ $state->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-phoenix-secondary">{{ __('Filter') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-4">
        @forelse ($this->widgets as $widget)
            <div class="col-xl-6">
                <a href="{{ route('widgets.edit', $widget, false) }}"
                    class="card border border-translucent shadow-sm h-100 text-decoration-none text-reset">
                    <div class="card-body d-flex flex-column gap-3">
                        <div class="d-flex align-items-start justify-content-between gap-3">
                            <div>
                                <div class="small text-uppercase fw-semibold text-body-tertiary mb-1">
                                    {{ $widget->type->label() }}
                                </div>
                                <h2 class="h5 mb-1">{{ $widget->displayName() }}</h2>
                                <p class="text-body-secondary mb-0">
                                    {{ __('Updated :time', ['time' => $widget->updated_at->format('j M Y, H:i')]) }}
                                </p>
                            </div>

                            <span class="badge badge-phoenix badge-phoenix-secondary">
                                {{ $widget->healthLabel() }}
                            </span>
                        </div>

                        <div class="row g-3 small">
                            <div class="col-sm-4">
                                <div class="text-body-secondary">{{ __('Canvas usage') }}</div>
                                <div class="fw-semibold">{{ $widget->usage_count }}</div>
                            </div>
                            <div class="col-sm-4">
                                <div class="text-body-secondary">{{ __('Standalone URL') }}</div>
                                <div class="fw-semibold">
                                    {{ __($widget->standaloneStatusLabel()) }}</div>
                            </div>
                            <div class="col-sm-4">
                                <div class="text-body-secondary">{{ __('Schema') }}</div>
                                <div class="fw-semibold">v{{ $widget->schema_version }}</div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        @empty
            <div class="col-12">
                <div class="card border border-translucent shadow-sm">
                    <div class="card-body text-body-secondary">
                        {{ __('No widgets match the current filters yet.') }}
                    </div>
                </div>
            </div>
        @endforelse
    </div>
</div>
