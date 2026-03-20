@props(['item'])

@php
    $children = $item['children'] ?? [];
    $collapseId = 'shell-nav-'.$item['id'];
@endphp

<div class="nav-item-wrapper">
    @if ($children !== [])
        <a
            class="nav-link dropdown-indicator label-1 {{ $item['active'] ? 'active' : '' }}"
            href="#{{ $collapseId }}"
            role="button"
            data-bs-toggle="collapse"
            aria-expanded="{{ $item['active'] ? 'true' : 'false' }}"
            aria-controls="{{ $collapseId }}"
        >
                <div class="d-flex align-items-center">
                    <div class="dropdown-indicator-icon-wrapper">
                        <span class="fas fa-caret-right dropdown-indicator-icon"></span>
                    </div>
                <span class="nav-link-icon"><span class="{{ $item['icon'] }}"></span></span>
                <span class="nav-link-text">{{ $item['label'] }}</span>
            </div>
        </a>

        <div class="parent-wrapper label-1">
            <ul class="nav collapse parent {{ $item['active'] ? 'show' : '' }}" id="{{ $collapseId }}">
                @foreach ($children as $child)
                    <li class="nav-item">
                        <x-shell.vertical-nav-item :item="$child" />
                    </li>
                @endforeach
            </ul>
        </div>
    @else
        <a class="nav-link label-1 {{ $item['active'] ? 'active' : '' }}" href="{{ $item['href'] }}">
            <div class="d-flex align-items-center">
                <span class="nav-link-icon"><span class="{{ $item['icon'] }}"></span></span>
                <span class="nav-link-text">{{ $item['label'] }}</span>
            </div>
        </a>
    @endif
</div>
