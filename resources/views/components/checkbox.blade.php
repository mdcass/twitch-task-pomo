@props(['checked' => false])

<input type="checkbox" @checked($checked) {{ $attributes->merge(['class' => 'form-check-input']) }}>
