@props(['align' => 'right', 'width' => '48', 'contentClasses' => '', 'dropdownClasses' => ''])

@php
$alignmentClasses = match ($align) {
    'left' => 'start-0',
    'top' => '',
    'none', 'false' => '',
    default => 'end-0',
};

$width = match ($width) {
    '48' => '12rem',
    '60' => '15rem',
    default => '12rem',
};
@endphp

<div class="position-relative" x-data="{ open: false }" @click.away="open = false" @close.stop="open = false">
    <div @click="open = ! open">
        {{ $trigger }}
    </div>

    <div x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="transform opacity-0 scale-95"
            x-transition:enter-end="transform opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="transform opacity-100 scale-100"
            x-transition:leave-end="transform opacity-0 scale-95"
            class="dropdown-menu show mt-2 shadow border border-translucent {{ $alignmentClasses }} {{ $dropdownClasses }}"
            style="display: none; width: {{ $width }};"
            @click="open = false">
        <div class="{{ $contentClasses }}">
            {{ $content }}
        </div>
    </div>
</div>
