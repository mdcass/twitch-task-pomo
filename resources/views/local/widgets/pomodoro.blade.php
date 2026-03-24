<x-local-tooling-layout :title="$title">
    <main class="container-fluid min-vh-100 py-5">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-12 col-lg-7 col-xxl-5">
                <div class="card border-0 shadow-lg">
                    <div class="card-body text-center">
                        <div class="small text-uppercase fw-bold text-body-tertiary mb-2">Remaining</div>
                        <div class="display-3 fw-bold mb-2" data-pomodoro-countdown
                             @if ($countdownTarget !== null) data-countdown-target="{{ $countdownTarget }}" @endif>
                            {{ $countdownDisplay }}
                        </div>
                        <p class="text-body-secondary mb-0">
                            <span
                                class="badge rounded-pill px-3 py-2 {{ $stateBadgeClass }}">
                                {{ $stateLabel }}
                                / Focus {{ $focusMinutes }} min
                                / Break {{ $breakMinutes }} min
                            </span>
                        </p>
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
