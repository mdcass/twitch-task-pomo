@props([
    'as' => 'a',
    'href' => '#',
    'icon',
    'iconColorClass' => 'text-body',
    'type' => 'button',
])

@if ($as === 'button')
    <button type="{{ $type }}"
        {{ $attributes->merge(['class' => 'btn btn-phoenix-secondary w-100 d-flex align-items-center justify-content-center gap-2']) }}>
        <span class="{{ $icon }} {{ $iconColorClass }} fs-9"></span>
        <span>{{ $slot }}</span>
    </button>
@else
    <a href="{{ $href }}"
        {{ $attributes->merge(['class' => 'btn btn-phoenix-secondary w-100 d-flex align-items-center justify-content-center gap-2']) }}>
        <span class="{{ $icon }} {{ $iconColorClass }} fs-9"></span>
        <span>{{ $slot }}</span>
    </a>
@endif
