@php
    $productName = config('app.name') === 'Laravel' ? 'Twitch Task Pomo' : config('app.name');
@endphp

<a href="{{ url('/') }}" class="twitch-brand-lockup">
    <span class="twitch-brand-mark">
        <x-application-mark class="w-100 h-100" />
    </span>

    <span>
        <span class="twitch-brand-subtitle">Twitch Overlay Platform</span>
        <span class="twitch-brand-title">{{ $productName }}</span>
    </span>
</a>
