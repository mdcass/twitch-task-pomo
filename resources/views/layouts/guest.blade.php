@props(['variant'])

@php
    $productName = config('app.name') === 'Laravel' ? 'Twitch Task Pomo' : config('app.name');
    $validVariants = ['simple', 'card'];

    if (!in_array($variant, $validVariants, true)) {
        throw new InvalidArgumentException("Unsupported auth layout variant [{$variant}].");
    }
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $productName }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@300;400;600;700;800;900&display=swap"
        rel="stylesheet">

    <script>
        (() => {
            const storedTheme = localStorage.getItem('phoenixTheme') ?? 'light';
            const theme = storedTheme === 'auto' ?
                (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light') :
                storedTheme;

            document.documentElement.setAttribute('data-bs-theme', theme);
        })();
    </script>

    @vite(['resources/css/phoenix/app.scss', 'resources/js/phoenix/app.js'])

    @livewireStyles
</head>

<body>
    <main class="main min-vh-100" id="top">
        @if ($variant === 'simple')
            <div class="container">
                <div class="row flex-center min-vh-100 py-5">
                    <div class="col-sm-10 col-md-8 col-lg-5 col-xl-5 col-xxl-3">
                        {{ $slot }}
                    </div>
                </div>
            </div>
        @else
            <div class="container-fluid bg-body-tertiary dark__bg-gray-1200">
                <div class="bg-holder bg-auth-card-overlay"
                    style="background-image:url({{ asset('images/auth/phoenix-auth-bg-37.png') }});">
                </div>
                <div class="row flex-center position-relative min-vh-100 g-0 py-5">
                    <div class="col-11 col-sm-10 col-xl-8">
                        {{ $slot }}
                    </div>
                </div>
            </div>
        @endif
    </main>

    @livewireScripts
</body>

</html>
