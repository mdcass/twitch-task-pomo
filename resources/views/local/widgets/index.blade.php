<x-local-tooling-layout title="Local Widget Launcher">
    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-12 col-xxl-10">
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-4 mb-4">
                    <div>
                        <div class="small text-uppercase fw-bold text-body-tertiary mb-2">Local Tooling</div>
                        <h1 class="h2 mb-2">External Widget Preview Launcher</h1>
                        <p class="text-body-secondary mb-0">
                            Build local-only widget preview URLs and manage the Spotify test account used by the
                            OBS-safe
                            now-playing widget.
                        </p>
                    </div>

                    <div class="d-flex flex-column gap-2">
                        <a class="btn btn-phoenix-secondary" href="{{ $defaultTaskPreviewUrl }}" target="_blank"
                            rel="noopener">
                            Open Task Example
                        </a>
                        <a class="btn btn-phoenix-secondary" href="{{ $defaultFocusPreviewUrl }}" target="_blank"
                            rel="noopener">
                            Open Focus Example
                        </a>
                        <a class="btn btn-phoenix-secondary" href="{{ $defaultSpotifyPreviewUrl }}" target="_blank"
                            rel="noopener">
                            Open Spotify Widget
                        </a>
                    </div>
                </div>

                @if ($errors->has('spotify'))
                    <div class="alert alert-danger mb-4">
                        {{ $errors->first('spotify') }}
                    </div>
                @endif

                @session('status')
                    <div class="alert alert-success mb-4">
                        {{ $value }}
                    </div>
                @endsession

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <div class="small text-uppercase fw-bold text-body-tertiary mb-2">Spotify</div>
                        <div class="d-flex flex-column flex-xl-row justify-content-between gap-4">
                            <div class="flex-grow-1">
                                <h2 class="h4 mb-2">Manage the now-playing widget source</h2>
                                <p class="text-body-secondary mb-4">
                                    The public Spotify widget is local/testing-only and resolves the most recently
                                    linked
                                    Spotify auth record. Use this launcher to connect or reconnect the account you want
                                    OBS to render.
                                </p>

                                <div class="row g-3 mb-4">
                                    <div class="col-12 col-lg-6">
                                        <div class="rounded-4 border bg-body px-4 py-3 h-100">
                                            <div class="small text-uppercase fw-bold text-body-tertiary mb-2">Your
                                                connection</div>
                                            @if ($currentUserSpotifyAuth !== null)
                                                <div class="fw-semibold mb-1">
                                                    {{ $currentUserSpotifyAuth->profile['display_name'] ?? ($currentUserSpotifyAuth->provider_email ?? 'Spotify account linked') }}
                                                </div>
                                                <div class="small text-body-secondary">
                                                    Linked
                                                    {{ $currentUserSpotifyAuth->last_used_at?->diffForHumans() ?? 'recently' }}
                                                </div>
                                            @else
                                                <div class="fw-semibold mb-1">Not connected</div>
                                                <div class="small text-body-secondary">Connect Spotify to seed the
                                                    widget with your current playback.</div>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="col-12 col-lg-6">
                                        <div class="rounded-4 border bg-body px-4 py-3 h-100">
                                            <div class="small text-uppercase fw-bold text-body-tertiary mb-2">Public
                                                widget source</div>
                                            @if ($latestSpotifyAuth !== null)
                                                <div class="fw-semibold mb-1">
                                                    {{ $latestSpotifyAuth->profile['display_name'] ?? ($latestSpotifyAuth->provider_email ?? 'Spotify account linked') }}
                                                </div>
                                                <div class="small text-body-secondary">
                                                    Latest linked Spotify auth in local/testing.
                                                </div>
                                            @else
                                                <div class="fw-semibold mb-1">No Spotify source available</div>
                                                <div class="small text-body-secondary">The public widget will show a
                                                    reconnect state until an account is linked.</div>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex flex-column flex-sm-row gap-2">
                                    @if ($spotifyConfigured)
                                        <a class="btn btn-primary"
                                            href="{{ route('local.widgets.spotify.connect', absolute: false) }}">
                                            {{ $currentUserSpotifyAuth !== null ? 'Reconnect Spotify' : 'Connect Spotify' }}
                                        </a>
                                    @else
                                        <button type="button" class="btn btn-primary" disabled>
                                            Connect Spotify
                                        </button>
                                    @endif

                                    @if ($currentUserSpotifyAuth !== null)
                                        <form method="POST"
                                            action="{{ route('local.widgets.spotify.disconnect', absolute: false) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-phoenix-secondary w-100">
                                                Disconnect Spotify
                                            </button>
                                        </form>
                                    @endif

                                    <a class="btn btn-phoenix-secondary" href="{{ $defaultSpotifyPreviewUrl }}"
                                        target="_blank" rel="noopener">
                                        Open Spotify Widget
                                    </a>
                                </div>
                            </div>

                            <div class="flex-shrink-0" style="min-width: min(100%, 24rem);">
                                <div class="rounded-4 bg-body-tertiary p-4 h-100">
                                    <div class="small text-uppercase fw-bold text-body-tertiary mb-2">Route
                                        contract</div>
                                    <label class="form-label small" for="spotify-widget-url">Widget URL</label>
                                    <input id="spotify-widget-url" type="text" class="form-control mb-3" readonly
                                        value="{{ url($defaultSpotifyPreviewUrl) }}">
                                    <p class="small text-body-secondary mt-3 mb-0">
                                        Launcher access requires app auth. The widget route stays public in
                                        local/testing so it can load inside OBS, while polling is handled internally by
                                        Livewire.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-12 col-xl-6">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body p-4">
                                <div class="small text-uppercase fw-bold text-body-tertiary mb-2">Task List</div>
                                <h2 class="h4 mb-3">Generate a task widget URL</h2>

                                <form action="{{ route('local.widgets.task-list', absolute: false) }}"
                                    data-widget-builder="task-list">
                                    <div class="mb-3">
                                        <label class="form-label" for="task-list-title">Title</label>
                                        <input id="task-list-title" name="title" type="text" class="form-control"
                                            value="Focus Queue">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label" for="task-list-pending">Pending tasks</label>
                                        <textarea id="task-list-pending" name="pending_lines" class="form-control" rows="5">Plan stream outline
Refine camera framing</textarea>
                                        <div class="form-text">One task per line. Each line becomes a repeated
                                            <code>pending[]</code> query param.
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label" for="task-list-completed">Completed tasks</label>
                                        <textarea id="task-list-completed" name="completed_lines" class="form-control" rows="3">Warm up intro scene</textarea>
                                        <div class="form-text">One task per line. Each line becomes a repeated
                                            <code>completed[]</code> query param.
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label" for="task-list-url">Generated URL</label>
                                        <input id="task-list-url" type="text" class="form-control" readonly
                                            data-generated-url>
                                    </div>

                                    <button type="submit" class="btn btn-primary w-100">Generate and Open Task
                                        Preview</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-xl-6">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body p-4">
                                <div class="small text-uppercase fw-bold text-body-tertiary mb-2">Pomodoro</div>
                                <h2 class="h4 mb-3">Generate a pomodoro widget URL</h2>

                                <form action="{{ route('local.widgets.pomodoro', absolute: false) }}"
                                    data-widget-builder="pomodoro">
                                    <div class="mb-3">
                                        <label class="form-label" for="pomodoro-title">Title</label>
                                        <input id="pomodoro-title" name="title" type="text"
                                            class="form-control" value="Deep Work Sprint">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label" for="pomodoro-state">State</label>
                                        <select id="pomodoro-state" name="state" class="form-select"
                                            data-pomodoro-state>
                                            <option value="focus" selected>Focus</option>
                                            <option value="break">Break</option>
                                            <option value="paused">Paused</option>
                                        </select>
                                    </div>

                                    <div class="row g-3 mb-3">
                                        <div class="col-sm-6">
                                            <label class="form-label" for="pomodoro-focus-minutes">Focus
                                                minutes</label>
                                            <input id="pomodoro-focus-minutes" name="focus_minutes" type="number"
                                                min="1" max="180" class="form-control" value="25">
                                        </div>

                                        <div class="col-sm-6">
                                            <label class="form-label" for="pomodoro-break-minutes">Break
                                                minutes</label>
                                            <input id="pomodoro-break-minutes" name="break_minutes" type="number"
                                                min="1" max="60" class="form-control" value="5">
                                        </div>
                                    </div>

                                    <div class="mb-3" data-pomodoro-field="ends_at">
                                        <label class="form-label" for="pomodoro-ends-at">Ends at</label>
                                        <input id="pomodoro-ends-at" name="ends_at" type="datetime-local"
                                            class="form-control" value="{{ $defaultFocusEndsAt }}">
                                        <div class="form-text">Used for focus and break countdown states.</div>
                                    </div>

                                    <div class="mb-3 d-none" data-pomodoro-field="remaining_seconds">
                                        <label class="form-label" for="pomodoro-remaining-seconds">Remaining
                                            seconds</label>
                                        <input id="pomodoro-remaining-seconds" name="remaining_seconds"
                                            type="number" min="0" max="21600" class="form-control"
                                            value="720">
                                        <div class="form-text">Used only when the timer is paused.</div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label" for="pomodoro-url">Generated URL</label>
                                        <input id="pomodoro-url" type="text" class="form-control" readonly
                                            data-generated-url>
                                    </div>

                                    <button type="submit" class="btn btn-primary w-100">Generate and Open Pomodoro
                                        Preview</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mt-1">
                    <div class="col-12 col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body p-4">
                                <div class="small text-uppercase fw-bold text-body-tertiary mb-2">Quick Links</div>
                                <h2 class="h5 mb-3">Canonical examples</h2>
                                <div class="d-grid gap-2">
                                    <a class="btn btn-phoenix-secondary" href="{{ $defaultTaskPreviewUrl }}"
                                        target="_blank" rel="noopener">Open task list sample</a>
                                    <a class="btn btn-phoenix-secondary" href="{{ $defaultFocusPreviewUrl }}"
                                        target="_blank" rel="noopener">Open focus countdown sample</a>
                                    <a class="btn btn-phoenix-secondary" href="{{ $defaultPausedPreviewUrl }}"
                                        target="_blank" rel="noopener">Open paused timer sample</a>
                                    <a class="btn btn-phoenix-secondary" href="{{ $defaultSpotifyPreviewUrl }}"
                                        target="_blank" rel="noopener">Open Spotify now playing sample</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body p-4">
                                <div class="small text-uppercase fw-bold text-body-tertiary mb-2">Contract</div>
                                <h2 class="h5 mb-3">Route shape</h2>
                                <p class="text-body-secondary mb-2"><code>/local/widgets/task-list</code> supports
                                    <code>title</code>, repeated <code>pending[]</code>, and repeated
                                    <code>completed[]</code>.
                                </p>
                                <p class="text-body-secondary mb-0"><code>/local/widgets/pomodoro</code> supports
                                    <code>title</code>, <code>state</code>, <code>focus_minutes</code>,
                                    <code>break_minutes</code>, plus either <code>ends_at</code> or
                                    <code>remaining_seconds</code> depending on the state.
                                </p>
                                <p class="text-body-secondary mb-0 mt-2"><code>/local/widgets/spotify</code> renders
                                    a Livewire now-playing widget that polls every 15 seconds for local/testing
                                    previews.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const trimLines = (value) => value
                    .split(/\r?\n/)
                    .map((line) => line.trim())
                    .filter(Boolean);

                const buildTaskListUrl = (form) => {
                    const url = new URL(form.action, window.location.origin);
                    const formData = new FormData(form);

                    const title = String(formData.get('title') ?? '').trim();

                    if (title !== '') {
                        url.searchParams.set('title', title);
                    }

                    trimLines(String(formData.get('pending_lines') ?? '')).forEach((item) => {
                        url.searchParams.append('pending[]', item);
                    });

                    trimLines(String(formData.get('completed_lines') ?? '')).forEach((item) => {
                        url.searchParams.append('completed[]', item);
                    });

                    return url;
                };

                const syncPomodoroFields = (form) => {
                    const state = form.querySelector('[data-pomodoro-state]')?.value ?? 'focus';
                    const endsAtField = form.querySelector('[data-pomodoro-field="ends_at"]');
                    const remainingField = form.querySelector('[data-pomodoro-field="remaining_seconds"]');
                    const endsAtInput = form.querySelector('[name="ends_at"]');
                    const remainingInput = form.querySelector('[name="remaining_seconds"]');

                    const paused = state === 'paused';

                    endsAtField?.classList.toggle('d-none', paused);
                    remainingField?.classList.toggle('d-none', !paused);

                    if (endsAtInput instanceof HTMLInputElement) {
                        endsAtInput.disabled = paused;
                    }

                    if (remainingInput instanceof HTMLInputElement) {
                        remainingInput.disabled = !paused;
                    }
                };

                const buildPomodoroUrl = (form) => {
                    const url = new URL(form.action, window.location.origin);
                    const formData = new FormData(form);
                    const title = String(formData.get('title') ?? '').trim();
                    const state = String(formData.get('state') ?? 'focus');

                    if (title !== '') {
                        url.searchParams.set('title', title);
                    }

                    url.searchParams.set('state', state);
                    url.searchParams.set('focus_minutes', String(formData.get('focus_minutes') ?? '25'));
                    url.searchParams.set('break_minutes', String(formData.get('break_minutes') ?? '5'));

                    if (state === 'paused') {
                        url.searchParams.set('remaining_seconds', String(formData.get('remaining_seconds') ??
                            '720'));
                    } else {
                        const endsAt = String(formData.get('ends_at') ?? '').trim();

                        if (endsAt !== '') {
                            url.searchParams.set('ends_at', endsAt);
                        }
                    }

                    return url;
                };

                document.querySelectorAll('[data-widget-builder]').forEach((form) => {
                    if (!(form instanceof HTMLFormElement)) {
                        return;
                    }

                    if (form.dataset.widgetBuilder === 'pomodoro') {
                        syncPomodoroFields(form);

                        form.querySelector('[data-pomodoro-state]')?.addEventListener('change', () => {
                            syncPomodoroFields(form);
                        });
                    }

                    form.addEventListener('submit', (event) => {
                        event.preventDefault();

                        const url = form.dataset.widgetBuilder === 'task-list' ?
                            buildTaskListUrl(form) :
                            buildPomodoroUrl(form);

                        const output = form.querySelector('[data-generated-url]');

                        if (output instanceof HTMLInputElement) {
                            output.value = url.toString();
                        }

                        window.open(url.toString(), '_blank', 'noopener');
                    });
                });
            });
        </script>
    @endpush
</x-local-tooling-layout>
