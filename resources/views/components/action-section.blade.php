<div {{ $attributes->merge(['class' => 'row g-4 align-items-start']) }}>
    <div class="col-lg-4">
        <x-section-title>
            <x-slot name="title">{{ $title }}</x-slot>
            <x-slot name="description">{{ $description }}</x-slot>
        </x-section-title>
    </div>

    <div class="col-lg-8">
        <div class="card border border-translucent shadow-sm">
            <div class="card-body p-4">
                {{ $content }}
            </div>
        </div>
    </div>
</div>
