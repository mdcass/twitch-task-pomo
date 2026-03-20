@props([
    'id',
    'name',
    'label',
    'icon',
    'type' => 'text',
    'value' => null,
])

<div {{ $attributes->only('class')->merge(['class' => 'mb-3 text-start']) }}>
    <x-auth.field-label :for="$id" :value="$label" />
    <div class="form-icon-container">
        <x-input
            :id="$id"
            class="form-icon-input"
            :type="$type"
            :name="$name"
            :value="$value"
            {{ $attributes->except(['class']) }}
        />
        <span class="{{ $icon }} text-body fs-9 form-icon"></span>
    </div>
</div>
