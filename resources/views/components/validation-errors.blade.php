@if ($errors->any())
    <div {{ $attributes->merge(['class' => 'alert alert-outline-danger']) }}>
        <div class="fw-semibold mb-2">{{ __('There was a problem') }}</div>

        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
