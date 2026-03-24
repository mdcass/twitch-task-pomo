<div class="widget-task-list-shell">
    <header class="overlay-widget-card__header">
        <div>
            <div class="overlay-widget-card__eyebrow">{{ __('Task Queue') }}</div>
            <div class="overlay-widget-card__title">{{ $title }}</div>
        </div>

        <div class="overlay-widget-card__meta">
            {{ count($pendingItems) }} {{ __('pending') }}
            <span aria-hidden="true">/</span>
            {{ count($completedItems) }} {{ __('done') }}
        </div>
    </header>

    <div class="overlay-widget-card__body">
        @if ($pendingItems === [] && $completedItems === [])
            <div class="overlay-widget-card__empty">{{ __('No tasks configured yet.') }}</div>
        @else
            <div class="widget-task-list">
                @forelse ($pendingItems as $item)
                    <div class="widget-task-list__item">
                        <span class="widget-task-list__pill is-next">{{ __('Next') }}</span>
                        <span class="widget-task-list__label">{{ $item }}</span>
                    </div>
                @empty
                    <div class="widget-task-list__item is-muted">
                        <span class="widget-task-list__pill is-empty">{{ __('Queue') }}</span>
                        <span class="widget-task-list__label">{{ __('No pending items.') }}</span>
                    </div>
                @endforelse

                @foreach ($completedItems as $item)
                    <div class="widget-task-list__item is-completed">
                        <span class="widget-task-list__pill is-done">{{ __('Done') }}</span>
                        <span class="widget-task-list__label">{{ $item }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
