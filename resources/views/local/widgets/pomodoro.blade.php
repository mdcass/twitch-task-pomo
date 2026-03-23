<x-local-tooling-layout :title="$title">
    <main class="container-fluid min-vh-100 py-5">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-12 col-lg-7 col-xxl-5">
                <div class="card border-0 shadow-lg">
                    <div class="card-body p-4 p-lg-5">
                        <div class="d-flex flex-column flex-sm-row justify-content-between gap-3 mb-4">
                            <div>
                                <div class="small text-uppercase fw-bold text-body-tertiary mb-2">Local Widget
                                    Preview</div>
                                <h1 class="h2 mb-2">{{ $title }}</h1>
                                <p class="text-body-secondary mb-0">{{ $stateSummary }}</p>
                            </div>

                            <div class="d-flex align-items-start">
                                <span
                                    class="badge rounded-pill px-3 py-2 {{ $stateBadgeClass }}">{{ $stateLabel }}</span>
                            </div>
                        </div>

                        <div class="rounded-4 bg-body-tertiary p-4 p-lg-5 text-center mb-4">
                            <div class="small text-uppercase fw-bold text-body-tertiary mb-2">Remaining</div>
                            <div class="display-3 fw-bold mb-2" data-pomodoro-countdown
                                @if ($countdownTarget !== null) data-countdown-target="{{ $countdownTarget }}" @endif>
                                {{ $countdownDisplay }}
                            </div>
                            <p class="text-body-secondary mb-0">
                                {{ $state === 'paused' ? 'Frozen preview state' : 'Client-side countdown preview' }}
                            </p>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-sm-6">
                                <div class="rounded-4 border bg-body px-4 py-3 h-100">
                                    <div class="small text-uppercase fw-bold text-body-tertiary mb-2">Focus Length
                                    </div>
                                    <div class="h4 mb-0">{{ $focusMinutes }} min</div>
                                </div>
                            </div>

                            <div class="col-12 col-sm-6">
                                <div class="rounded-4 border bg-body px-4 py-3 h-100">
                                    <div class="small text-uppercase fw-bold text-body-tertiary mb-2">Break Length
                                    </div>
                                    <div class="h4 mb-0">{{ $breakMinutes }} min</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    @if ($countdownTarget !== null)
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const countdown = document.querySelector('[data-pomodoro-countdown]');

                    if (!(countdown instanceof HTMLElement)) {
                        return;
                    }

                    const target = countdown.dataset.countdownTarget;

                    if (!target) {
                        return;
                    }

                    const targetTimestamp = Date.parse(target);

                    const formatDuration = (remainingSeconds) => {
                        const clamped = Math.max(remainingSeconds, 0);
                        const minutes = Math.floor(clamped / 60);
                        const seconds = clamped % 60;

                        return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
                    };

                    const render = () => {
                        const remainingSeconds = Math.max(0, Math.floor((targetTimestamp - Date.now()) / 1000));
                        countdown.textContent = formatDuration(remainingSeconds);
                    };

                    render();
                    window.setInterval(render, 1000);
                });
            </script>
        @endpush
    @endif
</x-local-tooling-layout>
