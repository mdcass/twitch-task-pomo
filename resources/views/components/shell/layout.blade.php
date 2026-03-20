@props([
    'layout' => 'vertical',
    'layoutDefinition' => [],
    'sections' => [],
    'topNavigationItems' => [],
    'utility' => [],
    'productName',
])

@php
    $activeSection = collect($sections)->firstWhere('active', true) ?? ($sections[0] ?? null);
    $topNavId = match ($layout) {
        'dual-nav' => 'dualNav',
        'combo' => 'navbarCombo',
        'topnav-slim' => 'topNavSlim',
        'horizontal' => 'navbarHorizontal',
        default => 'navbarDefault',
    };
    $topNavAttributes = match ($layout) {
        'combo' => ['data-navbar-top' => 'combo', 'data-move-target' => '#navbarVerticalNav'],
        'topnav-slim' => ['data-navbar-appearance' => 'darker'],
        default => [],
    };
    $navbarAppearance = $topNavAttributes['data-navbar-appearance'] ?? null;
    $navbarTop = $topNavAttributes['data-navbar-top'] ?? null;
    $moveTarget = $topNavAttributes['data-move-target'] ?? null;
@endphp

@if ($layoutDefinition['sidebar'])
    <nav class="navbar navbar-vertical navbar-expand-lg">
        <div class="collapse navbar-collapse" id="navbarVerticalCollapse">
            <div class="navbar-vertical-content">
                <div class="d-lg-none px-3 pt-3 pb-2">
                    <x-shell.brand :product-name="$productName" />
                </div>

                @if ($layout === 'combo')
                    <x-shell.vertical-nav :sections="$activeSection ? [$activeSection] : []" nav-id="navbarVerticalNav" />
                @else
                    <x-shell.vertical-nav :sections="$sections" nav-id="navbarVerticalNav" />
                @endif
            </div>
        </div>

        <div class="navbar-vertical-footer">
            <button type="button" class="btn navbar-vertical-toggle border-0 fw-semibold w-100 white-space-nowrap d-flex align-items-center">
                <span class="uil uil-left-arrow-to-left fs-8"></span>
                <span class="uil uil-arrow-from-right fs-8"></span>
                <span class="navbar-vertical-footer-text ms-2">{{ __('Collapsed View') }}</span>
            </button>
        </div>
    </nav>
@endif

<nav
    id="{{ $topNavId }}"
    @class([
        $layoutDefinition['top_nav_class'],
        'navbar-top',
        'fixed-top',
        'navbar-expand-lg' => in_array($layout, ['horizontal', 'combo', 'dual-nav'], true),
    ])
    @if ($navbarAppearance)
        data-navbar-appearance="{{ $navbarAppearance }}"
    @endif
    @if ($navbarTop)
        data-navbar-top="{{ $navbarTop }}"
    @endif
    @if ($moveTarget)
        data-move-target="{{ $moveTarget }}"
    @endif
>
    @if ($layout === 'dual-nav')
        <div class="w-100">
            <div class="d-flex flex-between-center dual-nav-first-layer">
                <div class="navbar-logo">
                    <button class="btn navbar-toggler navbar-toggler-humburger-icon hover-bg-transparent" type="button" data-bs-toggle="collapse" data-bs-target="#navbarTopCollapse" aria-controls="navbarTopCollapse" aria-expanded="false" aria-label="{{ __('Toggle navigation') }}">
                        <span class="navbar-toggle-icon"><span class="toggle-line"></span></span>
                    </button>

                    <x-shell.brand :product-name="$productName" />
                </div>

                <x-shell.utilities :utility="$utility" />
            </div>

            <x-shell.top-nav
                :items="$topNavigationItems"
                collapse-id="navbarTopCollapse"
                collapse-classes="navbar-top-collapse justify-content-center"
            />
        </div>
    @elseif (in_array($layout, ['horizontal', 'combo'], true))
        <div class="navbar-logo">
            <button
                class="btn navbar-toggler navbar-toggler-humburger-icon hover-bg-transparent"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#{{ $layoutDefinition['sidebar'] ? 'navbarVerticalCollapse' : 'navbarTopCollapse' }}"
                aria-controls="{{ $layoutDefinition['sidebar'] ? 'navbarVerticalCollapse' : 'navbarTopCollapse' }}"
                aria-expanded="false"
                aria-label="{{ __('Toggle navigation') }}"
            >
                <span class="navbar-toggle-icon"><span class="toggle-line"></span></span>
            </button>

            <x-shell.brand :product-name="$productName" />
        </div>

        <x-shell.top-nav
            :items="$topNavigationItems"
            collapse-id="navbarTopCollapse"
            collapse-classes="navbar-top-collapse order-1 order-lg-0 justify-content-center"
        />

        <x-shell.utilities :utility="$utility" />
    @else
        <div class="collapse navbar-collapse justify-content-between">
            <div class="navbar-logo">
                <button
                    class="btn navbar-toggler navbar-toggler-humburger-icon hover-bg-transparent"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#{{ $layoutDefinition['sidebar'] ? 'navbarVerticalCollapse' : 'navbarTopCollapse' }}"
                    aria-controls="{{ $layoutDefinition['sidebar'] ? 'navbarVerticalCollapse' : 'navbarTopCollapse' }}"
                    aria-expanded="false"
                    aria-label="{{ __('Toggle navigation') }}"
                >
                    <span class="navbar-toggle-icon"><span class="toggle-line"></span></span>
                </button>

                <x-shell.brand :product-name="$productName" />
            </div>

            <x-shell.utilities :utility="$utility" />
        </div>
    @endif
</nav>
