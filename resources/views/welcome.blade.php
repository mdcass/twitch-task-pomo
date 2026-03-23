@php
    $productName = config('app.name') === 'Laravel' ? 'Twitch Task Pomo' : config('app.name');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

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

            document.documentElement.setAttribute('data-bs-theme', theme);
        })();
    </script>

    @vite(['resources/css/phoenix/app.scss', 'resources/js/phoenix/app.js'])
</head>

<body class="twitch-auth-shell">
    <main class="container-xxl px-4 px-lg-5 py-5">
        <div class="row align-items-center min-vh-100 g-4">
            <div class="col-12 col-lg-6">
                <div class="twitch-brand-lockup mb-4">
                    <span class="twitch-brand-mark">
                        <x-application-mark class="w-100 h-100" />
                    </span>
                    <span>
                        <span class="twitch-brand-subtitle">Twitch Overlay Platform</span>
                        <span class="twitch-brand-title">{{ $productName }}</span>
                    </span>
                </div>

                <span class="twitch-auth-badge mb-3">Phase 1 Migration</span>
                <h1 class="display-4 fw-bold mb-3">Custom Bootstrap theme foundations are now in place.</h1>
                <p class="lead text-body-secondary mb-4">
                    The application shell has been migrated onto product-owned Bootstrap and Phoenix-inspired primitives
                    so future overlay, composer, and stream-management work can build on stable UI foundations.
                </p>

                <div class="d-flex flex-wrap gap-3">
                    <a href="{{ route('login') }}" class="btn btn-primary btn-lg">{{ __('Log in') }}</a>
                    <a href="{{ route('register') }}"
                        class="btn btn-phoenix-secondary btn-lg">{{ __('Create account') }}</a>
                </div>
            </div>

            <div class="col-12 col-lg-6">
                <div class="row g-4">
                    <div class="col-12">
                        <div class="twitch-stat-card p-4 p-lg-5">
                            <div class="small text-uppercase fw-bold text-body-tertiary mb-2">What changed</div>
                            <h2 class="h3 mb-3">Phoenix now serves as the design source, not the runtime app.</h2>
                            <p class="text-body-secondary mb-0">The purchased theme is committed for reference under
                                `resources/third-party/themes/phoenix-v1.24.0`, while Blade, Livewire, and Vite remain
                                fully product-owned.</p>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="twitch-stat-card p-4 h-100">
                            <div class="small text-uppercase fw-bold text-body-tertiary mb-2">Adopted</div>
                            <div class="fw-semibold mb-2">Auth shell and shared account surfaces</div>
                            <p class="text-body-secondary mb-0">Login, registration, password recovery, profile, teams,
                                and API tokens now share the new Bootstrap presentation.</p>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="twitch-stat-card p-4 h-100">
                            <div class="small text-uppercase fw-bold text-body-tertiary mb-2">Deferred</div>
                            <div class="fw-semibold mb-2">Heavy dashboard demos and vendor widgets</div>
                            <p class="text-body-secondary mb-0">Maps, charts, editors, and support chat remain
                                reference-only until a product feature needs them.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>

</html>
