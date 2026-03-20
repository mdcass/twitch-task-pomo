<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn btn-primary fw-semibold']) }}>
    {{ $slot }}
</button>
