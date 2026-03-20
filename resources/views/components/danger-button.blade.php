<button {{ $attributes->merge(['type' => 'button', 'class' => 'btn btn-danger fw-semibold']) }}>
    {{ $slot }}
</button>
