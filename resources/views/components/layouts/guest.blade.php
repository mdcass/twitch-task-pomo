@props(['variant' => 'card'])

<x-guest-layout :variant="$variant">
    {{ $slot }}
</x-guest-layout>
