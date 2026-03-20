@props([
    'sections' => [],
    'navId' => 'navbarVerticalNav',
])

<ul class="navbar-nav flex-column" id="{{ $navId }}">
    @foreach ($sections as $section)
        <li class="nav-item">
            <p class="navbar-vertical-label">{{ $section['label'] }}</p>
            <hr class="navbar-vertical-line">

            @foreach ($section['items'] as $item)
                <x-shell.vertical-nav-item :item="$item" />
            @endforeach
        </li>
    @endforeach
</ul>
