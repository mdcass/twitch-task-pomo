<x-app-layout>
    <x-slot name="contentTop">
        <x-content-top-nav>
            <x-slot name="primary">
                <x-breadcrumbs :items="[
                    ['label' => __('Dashboard'), 'href' => route('dashboard', absolute: false)],
                    ['label' => __('Widgets'), 'current' => true],
                ]" />
            </x-slot>
        </x-content-top-nav>
    </x-slot>

    <x-slot name="header">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <div>
                <h1 class="h2 mb-1">{{ __('Widgets') }}</h1>
                <p class="text-body-secondary mb-0">
                    {{ __('Manage reusable proprietary widgets for standalone URLs and canvas placement.') }}
                </p>
            </div>
        </div>
    </x-slot>

    <div class="d-flex flex-column gap-4">
        @if (session('status'))
            <div class="alert alert-subtle-success border border-success-subtle mb-0" role="alert">
                {{ session('status') }}
            </div>
        @endif

        <div class="card border border-translucent shadow-sm">
            <div class="card-body">
                <form method="POST" action="{{ route('widgets.store', absolute: false) }}" class="row g-3 align-items-end">
                    @csrf
                    <div class="col-md-4">
                        <label class="form-label" for="widget-type">{{ __('Widget type') }}</label>
                        <select id="widget-type" name="type" class="form-select @error('type') is-invalid @enderror">
                            @foreach ($widgetTypes as $type)
                                <option value="{{ $type->value }}">{{ $type->label() }}</option>
                            @endforeach
                        </select>
                        @error('type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-5">
                        <label class="form-label" for="widget-name">{{ __('Name') }}</label>
                        <input id="widget-name" type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                            value="{{ old('name') }}" placeholder="{{ __('Optional custom name') }}">
                        @error('name')
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

        <div class="card border border-translucent shadow-sm">
            <div class="card-body">
                <form method="GET" action="{{ route('widgets.index', absolute: false) }}" class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label" for="widget-search">{{ __('Search') }}</label>
                        <input id="widget-search" type="search" name="search" class="form-control"
                            value="{{ $search }}" placeholder="{{ __('Search widgets') }}">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="widget-filter-type">{{ __('Type') }}</label>
                        <select id="widget-filter-type" name="type" class="form-select">
                            <option value="">{{ __('All types') }}</option>
                            @foreach ($widgetTypes as $type)
                                <option value="{{ $type->value }}" @selected($selectedType === $type->value)>{{ $type->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label" for="widget-filter-lifecycle">{{ __('Status') }}</label>
                        <select id="widget-filter-lifecycle" name="lifecycle" class="form-select">
                            <option value="">{{ __('All states') }}</option>
                            @foreach ($lifecycleStates as $state)
                                <option value="{{ $state->value }}" @selected($selectedLifecycle === $state->value)>{{ $state->label() }}</option>
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
            @forelse ($widgets as $widget)
                <div class="col-xl-6">
                    <a href="{{ route('widgets.show', $widget->id, false) }}" class="card border border-translucent shadow-sm h-100 text-decoration-none text-reset">
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
                                    {{ $widget->lifecycle_state->label() }}
                                </span>
                            </div>

                            <div class="row g-3 small">
                                <div class="col-sm-4">
                                    <div class="text-body-secondary">{{ __('Canvas usage') }}</div>
                                    <div class="fw-semibold">{{ $widget->canvas_widgets_count }}</div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="text-body-secondary">{{ __('Standalone') }}</div>
                                    <div class="fw-semibold">{{ $widget->isPublished() ? __('Published') : __('Unpublished') }}</div>
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
</x-app-layout>
