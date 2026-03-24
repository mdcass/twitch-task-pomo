@if ($countdownTarget)
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const countdown = document.querySelector('[data-widget-countdown]');

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
