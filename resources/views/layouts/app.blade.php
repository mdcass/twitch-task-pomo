@props([
    'layout' => 'vertical',
    'layoutDefinition' => [],
])

@php
    $productName = config('app.name') === 'Laravel' ? 'Twitch Task Pomo' : config('app.name');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    data-navigation-type="{{ $layoutDefinition['navigation_type'] }}"
    data-navbar-horizontal-shape="{{ $layoutDefinition['navbar_horizontal_shape'] }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $productName }}</title>

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
            const sidebarCollapsed = localStorage.getItem('phoenixIsNavbarVerticalCollapsed') === 'true';

            document.documentElement.setAttribute('data-bs-theme', theme);
            document.documentElement.classList.toggle('navbar-vertical-collapsed', sidebarCollapsed);
        })();
    </script>

    @vite(['resources/css/phoenix/app.scss', 'resources/js/phoenix/app.js'])

    @livewireStyles
</head>

<body @class(['text-body', $layoutDefinition['body_class']])>
    <x-banner />

    <main class="main" data-shell-layout="{{ $layout }}" id="top">
        <x-shell.layout :layout="$layout" :layout-definition="$layoutDefinition" :sections="$sections" :top-navigation-items="$topNavigationItems" :utility="$utility"
            :product-name="$productName" />
        <div class="content">
            @if (isset($contentTop))
                <div data-content-top-shell>
                    {{ $contentTop }}
                </div>
            @endif

            @if (isset($header))
                <header>
                    {{ $header }}
                </header>
            @endif

            {{ $slot }}
        </div>
    </main>

    @stack('modals')

    @livewireScripts
</body>

</html>
