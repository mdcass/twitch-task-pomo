@props(['active'])

@php
$classes = ($active ?? false)
            ? 'dropdown-item active fw-semibold'
            : 'dropdown-item';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
