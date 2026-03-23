@props(['name', 'initials', 'url' => null, 'size' => 'l'])

<div {{ $attributes->class(['avatar', 'avatar-' . $size]) }}>
    @if ($url)
        <img class="rounded-circle" src="{{ $url }}" alt="{{ $name }}"
            onerror="this.classList.add('d-none'); this.nextElementSibling.classList.remove('d-none');">
    @endif

    <div @class(['avatar-name rounded-circle', 'd-none' => (bool) $url])>
        <span>{{ $initials }}</span>
    </div>
</div>
