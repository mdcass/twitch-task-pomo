@props(['submit'])

<div {{ $attributes->merge(['class' => 'row g-4 align-items-start']) }}>
    <div class="col-lg-4">
        <x-section-title>
            <x-slot name="title">{{ $title }}</x-slot>
            <x-slot name="description">{{ $description }}</x-slot>
        </x-section-title>
    </div>

    <div class="col-lg-8">
        <form wire:submit="{{ $submit }}">
            <div class="card border border-translucent shadow-sm">
                <div class="card-body p-4">
                    <div class="row g-3">
                        {{ $form }}
                    </div>
                </div>

                @if (isset($actions))
                    <div class="card-footer bg-body-tertiary d-flex align-items-center justify-content-end gap-3 flex-wrap">
                        {{ $actions }}
                    </div>
                @endif
            </div>
        </form>
    </div>
</div>
