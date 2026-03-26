<div class="card border-0 shadow-sm h-100">
    <div class="card-body d-flex flex-column justify-content-center align-items-center text-center gap-3">
        <div class="small text-uppercase fw-semibold text-body-tertiary">{{ __('Follower Goal') }}</div>
        <div class="h4 mb-0">{{ $title }}</div>
        <div class="display-6 fw-bold">{{ $currentCount }} / {{ $goalTarget }}</div>
        <div class="progress w-100" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $goalTarget }}"
            aria-valuenow="{{ $currentCount }}">
            <div class="progress-bar" style="width: {{ $progressPercent }}%;"></div>
        </div>
        <div class="text-body-secondary">
            {{ __(':count followers to go', ['count' => $remainingCount]) }}
            @if ($endDate)
                · {{ __('Ends :date', ['date' => $endDate->format('j M Y')]) }}
            @endif
        </div>
    </div>
</div>
