@php
    $productName = config('app.name') === 'Laravel' ? 'Twitch Task Pomo' : config('app.name');
@endphp

<span {{ $attributes->merge(['class' => 'twitch-brand-lockup']) }}>
    <span class="twitch-brand-mark">
        <x-application-mark class="w-100 h-100" />
    </span>

    <span>
        <span class="twitch-brand-subtitle">Twitch Overlay Platform</span>
        <span class="twitch-brand-title">{{ $productName }}</span>
    </span>
</span>
