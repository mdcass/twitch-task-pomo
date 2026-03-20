@props([
    'items' => [],
    'collapseId' => 'navbarTopCollapse',
    'collapseClasses' => 'navbar-top-collapse order-1 order-lg-0 justify-content-center',
])

<div class="collapse navbar-collapse {{ $collapseClasses }}" id="{{ $collapseId }}">
    <ul class="navbar-nav navbar-nav-top" data-dropdown-on-hover="data-dropdown-on-hover">
        @foreach ($items as $item)
            <x-shell.top-nav-item :item="$item" />
        @endforeach
    </ul>
</div>
