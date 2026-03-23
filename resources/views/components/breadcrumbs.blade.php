@props([
    'items' => [],
])

<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb mb-0">
        @foreach ($items as $item)
            @php
                $isCurrent = (bool) ($item['current'] ?? $item['active'] ?? false);
                $label = (string) ($item['label'] ?? '');
                $href = $item['href'] ?? null;
            @endphp

            <li @class(['breadcrumb-item', 'active text-body-highlight' => $isCurrent])
                @if ($isCurrent) aria-current="page" @endif>
                @if (! $isCurrent && is_string($href) && $href !== '')
                    <a class="text-body-emphasis" href="{{ $href }}">{{ $label }}</a>
                @else
                    {{ $label }}
                @endif
            </li>
        @endforeach
    </ol>
</nav>
