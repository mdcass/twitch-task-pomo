@props([
    'title' => null,
])

@php
    $productName = config('app.name') === 'Laravel' ? 'Twitch Task Pomo' : config('app.name');
    $documentTitle = filled($title) ? "{$title} · {$productName}" : $productName;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $documentTitle }}</title>
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

    @stack('head')
</head>

<body class="bg-body-tertiary">
    {{ $slot }}

    @stack('scripts')
</body>

</html>
