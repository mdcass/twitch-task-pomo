@php
    use App\Support\Branding\ProductBrand;
@endphp

<x-mail::layout>
    <x-slot:header>
        <x-mail::header :url="config('app.url')">
            {{ ProductBrand::productName() }}
        </x-mail::header>
    </x-slot:header>

    {{ $slot }}

    @isset($subcopy)
        <x-slot:subcopy>
            <x-mail::subcopy>
                {{ $subcopy }}
            </x-mail::subcopy>
        </x-slot:subcopy>
    @endisset

    <x-slot:footer>
        <x-mail::footer>
            © {{ date('Y') }} {{ ProductBrand::productName() }}. {{ ProductBrand::productTagline() }}.
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
