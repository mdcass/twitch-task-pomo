<div class="widget-pomodoro-shell">
    <header class="overlay-widget-card__header">
        <div>
            <div class="overlay-widget-card__eyebrow">{{ __('Pomodoro') }}</div>
            <div class="overlay-widget-card__title">{{ $title }}</div>
        </div>

        <span class="widget-pomodoro__badge {{ $stateBadgeClass }}">{{ $stateLabel }}</span>
    </header>

    <div class="overlay-widget-card__body overlay-widget-card__body--centered">
        <div class="widget-pomodoro__countdown" data-widget-countdown
            @if ($countdownTarget) data-countdown-target="{{ $countdownTarget }}" @endif>
            {{ $countdownDisplay }}
        </div>

        <div class="overlay-widget-card__meta">
            {{ __('Focus :focus / Break :break', ['focus' => $focusMinutes . ' min', 'break' => $breakMinutes . ' min']) }}
        </div>

        <div class="overlay-widget-card__summary">{{ $stateSummary }}</div>
    </div>
</div>
