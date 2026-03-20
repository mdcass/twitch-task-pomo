@props([
    'productName',
])

<a class="navbar-brand me-1 me-sm-3" href="{{ route('dashboard', absolute: false) }}">
    <div class="d-flex align-items-center">
        <div class="d-flex align-items-center">
            <span class="twitch-shell-brand-mark">
                <x-application-mark class="w-100 h-100" />
            </span>
            <h5 class="logo-text ms-2 d-none d-sm-block">{{ $productName }}</h5>
        </div>
    </div>
</a>
