@props([
    'title' => null,
])

@php
    $productName = config('app.name') === 'Laravel' ? 'Twitch Task Pomo' : config('app.name');
    $documentTitle = filled($title) ? "{$title} · {$productName}" : $productName;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $documentTitle }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/phoenix/app.scss'])
    @stack('head')
</head>

<body class="overlay-runtime-page overlay-widget-page">
    <main class="overlay-widget">
        {{ $slot }}
    </main>

    @stack('scripts')
</body>

</html>
