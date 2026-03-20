@props(['value'])

<label {{ $attributes->merge(['class' => 'form-label fw-bold text-uppercase text-body-tertiary small']) }}>
    {{ $value ?? $slot }}
</label>
