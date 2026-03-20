@props(['active'])

@php
$classes = ($active ?? false)
            ? 'nav-link active fw-semibold'
            : 'nav-link text-body-secondary fw-semibold';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
