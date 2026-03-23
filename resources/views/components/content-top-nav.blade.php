@php
    $primarySlot = isset($primary) && !$primary->isEmpty() ? $primary : (!$slot->isEmpty() ? $slot : null);
    $secondarySlot = isset($secondary) && !$secondary->isEmpty() ? $secondary : null;
@endphp

@if ($primarySlot || $secondarySlot)
    <div class="widgets-scrollspy-nav mt-n5 bg-body-emphasis z-5 mx-n4 mx-lg-n6 border-bottom twitch-content-top-nav mb-3"
        data-content-top-nav>
        @if ($primarySlot)
            <div class="twitch-content-top-nav__section twitch-content-top-nav__section--primary">
                <div class="simplebar-scrollspy navbar py-0 scrollbar-overlay twitch-content-top-nav__band"
                    data-content-top-scroller>
                    <div
                        class="d-inline-flex align-items-center w-100 py-3 twitch-content-top-nav__content twitch-content-top-nav__content--primary">
                        {{ $primarySlot }}
                    </div>
                </div>
            </div>
        @endif

        @if ($secondarySlot)
            <div class="twitch-content-top-nav__section twitch-content-top-nav__section--secondary">
                <div class="simplebar-scrollspy navbar py-0 scrollbar-overlay twitch-content-top-nav__band border-top"
                    data-content-top-scroller>
                    <div
                        class="d-inline-flex align-items-center w-100 px-4 px-lg-6 py-3 twitch-content-top-nav__content twitch-content-top-nav__content--secondary">
                        {{ $secondarySlot }}
                    </div>
                </div>
            </div>
        @endif
    </div>
@endif
