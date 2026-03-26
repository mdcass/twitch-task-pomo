<div class="d-flex flex-column gap-4">
    @if (session('status'))
        <div class="alert alert-subtle-success border border-success-subtle mb-0">
            {{ session('status') }}
        </div>
    @endif

    @error('integration')
        <div class="alert alert-subtle-danger border border-danger-subtle mb-0">
            {{ $message }}
        </div>
    @enderror

    @foreach ($this->providers as $providerRow)
        @php($provider = $providerRow['provider'])
        @php($auth = $providerRow['auth'])
        @php($widgets = $providerRow['widgets'])
        <section class="card border border-translucent shadow-sm">
            <div class="card-body d-flex flex-column gap-4">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3">
                    <div>
                        <div class="small text-uppercase fw-semibold text-body-tertiary mb-1">{{ __('Provider') }}</div>
                        <h2 class="h4 mb-1">{{ $provider->label() }}</h2>
                        <p class="text-body-secondary mb-0">
                            {{ __('Used by :count proprietary widget(s).', ['count' => $providerRow['usage_count']]) }}
                        </p>
                    </div>

                    <div class="d-flex flex-column align-items-lg-end gap-2">
                        <span
                            class="badge badge-phoenix {{ $auth ? 'badge-phoenix-success' : 'badge-phoenix-warning' }}">
                            {{ $auth ? __('Connected') : __('Not connected') }}
                        </span>

                        @if ($this->canManage)
                            <a class="btn btn-primary"
                                href="{{ route('integrations.redirect', ['provider' => $provider->value], false) }}">
                                {{ $auth ? __('Reconnect :provider', ['provider' => $provider->label()]) : __('Connect :provider', ['provider' => $provider->label()]) }}
                            </a>
                        @endif
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-lg-6">
                        <div class="rounded-3 border border-translucent p-3 h-100">
                            <div class="small text-uppercase fw-semibold text-body-tertiary mb-2">
                                {{ __('Owner connection') }}</div>
                            @if ($auth)
                                <div class="fw-semibold mb-1">
                                    {{ $auth->profile['display_name'] ?? ($auth->provider_email ?? $auth->provider_user_id) }}
                                </div>
                                <div class="small text-body-secondary">
                                    {{ __('Last used :time', ['time' => $auth->last_used_at?->diffForHumans() ?? __('recently')]) }}
                                </div>
                            @else
                                <div class="fw-semibold mb-1">{{ __('No owner connection') }}</div>
                                <div class="small text-body-secondary">
                                    {{ __('Widgets that depend on :provider will stay blocked until the current team owner connects it.', ['provider' => $provider->label()]) }}
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="rounded-3 border border-translucent p-3 h-100">
                            <div class="small text-uppercase fw-semibold text-body-tertiary mb-2">
                                {{ __('Owner-only controls') }}</div>
                            <div class="small text-body-secondary mb-3">
                                {{ __('Connect, reconnect, disconnect, and provider repair actions are restricted to the current team owner.') }}
                            </div>

                            @if ($this->canManage && $auth)
                                <button type="button" class="btn btn-phoenix-secondary"
                                    wire:click="disconnect('{{ $provider->value }}')">
                                    {{ __('Disconnect :provider', ['provider' => $provider->label()]) }}
                                </button>
                            @endif
                        </div>
                    </div>
                </div>

                <div>
                    <div class="small text-uppercase fw-semibold text-body-tertiary mb-2">{{ __('Dependent widgets') }}
                    </div>
                    @if ($widgets->isEmpty())
                        <div class="text-body-secondary small">{{ __('No widgets depend on this provider yet.') }}
                        </div>
                    @else
                        <div class="row g-3">
                            @foreach ($widgets as $widget)
                                <div class="col-xl-6">
                                    <a href="{{ route('widgets.edit', $widget, false) }}"
                                        class="d-block rounded-3 border border-translucent p-3 text-decoration-none text-reset h-100">
                                        <div class="fw-semibold mb-1">{{ $widget->displayName() }}</div>
                                        <div class="small text-body-secondary">
                                            {{ $widget->type->label() }} · {{ $widget->lifecycle_state->label() }}
                                        </div>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </section>
    @endforeach
</div>
