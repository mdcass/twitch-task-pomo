@props([
    'markOnly' => false,
    'brandClass' => '',
    'markClass' => '',
])

@php
    $productName = config('app.name') === 'Laravel' ? 'Twitch Task Pomo' : config('app.name');
@endphp

<a href="{{ url('/') }}" {{ $attributes->class(['d-flex flex-center text-decoration-none']) }}>
    <span @class(['twitch-auth-simple-brand', $brandClass])>
        <span @class(['twitch-auth-simple-brand-mark', $markClass])>
            <x-application-mark class="w-100 h-100" />
        </span>

        @unless ($markOnly)
            <span>
                <span class="twitch-auth-simple-brand-subtitle">Twitch Overlay Platform</span>
                <span class="twitch-auth-simple-brand-title">{{ $productName }}</span>
            </span>
        @endunless
    </span>
</a>
