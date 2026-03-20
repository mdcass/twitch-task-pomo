<span {{ $attributes->merge(['class' => 'twitch-brand-composite-mark']) }}>
    <span class="twitch-brand-composite-mark__base">
        <x-brand-marks.layer-group class="w-100 h-100" />
    </span>

    <span class="twitch-brand-composite-mark__badge">
        <span class="twitch-brand-composite-mark__badge-face">
            <x-brand-marks.stopwatch class="w-100 h-100" />
        </span>
    </span>
</span>
