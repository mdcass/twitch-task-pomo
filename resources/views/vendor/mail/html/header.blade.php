@php
    use App\Support\Branding\ProductBrand;
@endphp

@props(['url'])

<tr>
    <td class="header">
        <a href="{{ $url }}" class="brand-link">
            <img src="{{ ProductBrand::mailMarkUrl() }}" class="brand-mark" alt="{{ ProductBrand::productName() }}">
            <span class="brand-title">{{ ProductBrand::productName() }}</span>
        </a>
    </td>
</tr>
