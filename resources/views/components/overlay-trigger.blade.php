@props([
    'id',
    'surface',
    'action' => null,
    'title' => null,
    'data' => [],
    'as' => 'button',
])

@php
    $tag = $as;
    $defaultAttributes = $tag === 'button' ? ['type' => 'button'] : [];
    $resolvedAction = $action ?? ($data !== [] ? 'load' : 'open');
    $event = "overlay-{$surface}-{$resolvedAction}";
    $detail = ['id' => $id];

    if (is_string($title) && $title !== '') {
        $detail['title'] = $title;
    }

    if ($data !== []) {
        $detail['data'] = $data;
    }
@endphp

<{{ $tag }} {{ $attributes->merge($defaultAttributes) }} x-data
    x-on:click="window.dispatchEvent(new CustomEvent(@js($event), { detail: @js($detail) }))">
    {{ $slot }}
</{{ $tag }}>
