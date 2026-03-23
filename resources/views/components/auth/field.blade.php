@props(['id', 'name', 'label', 'type' => 'text', 'value' => null])

<div {{ $attributes->only('class')->merge(['class' => 'mb-3 text-start']) }}>
    <x-auth.field-label :for="$id" :value="$label" />
    <x-input :id="$id" :type="$type" :name="$name" :value="$value"
        {{ $attributes->except(['class']) }} />
</div>
