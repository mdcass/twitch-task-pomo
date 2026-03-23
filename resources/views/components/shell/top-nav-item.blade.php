@props(['item'])

@php
    $children = $item['children'] ?? [];
@endphp

@if ($children !== [])
    <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle lh-1 {{ $item['active'] ? 'active' : '' }}" href="#" role="button"
            data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-haspopup="true" aria-expanded="false">
            {{ $item['label'] }}
        </a>

        <ul class="dropdown-menu navbar-dropdown-caret">
            @foreach ($children as $child)
                <li>
                    <a class="dropdown-item {{ $child['active'] ? 'active' : '' }}" href="{{ $child['href'] }}">
                        <div class="dropdown-item-wrapper">
                            @if (!empty($child['icon']))
                                <span class="{{ $child['icon'] }} me-2"></span>
                            @endif
                            {{ $child['label'] }}
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>
    </li>
@else
    <li class="nav-item">
        <a class="nav-link lh-1 {{ $item['active'] ? 'active' : '' }}" href="{{ $item['href'] }}">
            {{ $item['label'] }}
        </a>
    </li>
@endif
