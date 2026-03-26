<form wire:submit="submit" class="d-flex flex-column gap-4">
    <p class="mb-0">
        {{ __('Quick-create a new widget and attach it to this canvas in one step. Provider-gated widgets attach immediately, then link you back to the full widget page for connect or repair work before they can render live.') }}
    </p>

    <div class="d-flex flex-column gap-3">
        @foreach ($widgetTypes as $widgetType)
            <label class="border rounded-3 p-3 d-flex align-items-start gap-3 cursor-pointer">
                <input type="radio" class="form-check-input mt-1" name="quick-create-widget-type"
                    value="{{ $widgetType->value }}" wire:model.live="fields.type">
                <div class="flex-grow-1">
                    <div class="fw-semibold">{{ $widgetType->label() }}</div>
                    <div class="small text-body-secondary">
                        @switch($widgetType)
                            @case(\App\Enums\Models\WidgetType::TaskList)
                                {{ __('Reusable task queue widget with shared pending and completed lists.') }}
                            @break

                            @case(\App\Enums\Models\WidgetType::Pomodoro)
                                {{ __('Reusable pomodoro timer with shared focus and break defaults.') }}
                            @break

                            @case(\App\Enums\Models\WidgetType::FollowerGoal)
                                {{ __('Twitch-backed goal bar that requires the team owner to connect Twitch.') }}
                            @break

                            @case(\App\Enums\Models\WidgetType::SpotifyNowPlaying)
                                {{ __('Spotify now playing card that requires the team owner to connect Spotify.') }}
                            @break
                        @endswitch
                    </div>
                </div>
            </label>
        @endforeach

        <x-input-error for="fields.type" class="mt-2" />
    </div>

    <div class="d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-phoenix-secondary" wire:click="cancel">
            {{ __('Cancel') }}
        </button>

        <button type="submit" class="btn btn-primary">
            {{ __('Quick-Create Widget') }}
        </button>
    </div>
</form>
