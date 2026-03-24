<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $widget->displayName() }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <style>
        html,
        body {
            margin: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            background: transparent;
        }

        .remote-widget-preview-shell {
            position: relative;
            width: 100%;
            height: 100%;
            overflow: hidden;
            background: transparent;
        }

        .remote-widget-preview-shell iframe {
            width: 100%;
            height: 100%;
            border: 0;
            display: block;
            background: transparent;
        }
    </style>
</head>

<body>
    <div class="remote-widget-preview-shell">
        <iframe src="{{ $widget->embed_url }}" sandbox="allow-scripts allow-same-origin"
            referrerpolicy="strict-origin-when-cross-origin" allowtransparency="true"
            data-remote-preview-frame></iframe>
    </div>

    <script>
        (() => {
            const parentOrigin = @js($parentOrigin);
            const token = @js($token);
            const widgetId = @js($widget->id);
            const frame = document.querySelector('[data-remote-preview-frame]');
            let settled = false;

            const emit = (event) => {
                if (!(window.parent && token)) {
                    return;
                }

                window.parent.postMessage({
                    type: 'overlay-widget-preview',
                    widgetId,
                    token,
                    event,
                }, parentOrigin);
            };

            if (!(frame instanceof HTMLIFrameElement)) {
                emit('error');
                return;
            }

            frame.addEventListener('load', () => {
                settled = true;
                emit('loaded');
            }, {
                once: true
            });

            frame.addEventListener('error', () => {
                if (settled) {
                    return;
                }

                settled = true;
                emit('error');
            }, {
                once: true
            });

            window.setTimeout(() => {
                if (settled) {
                    return;
                }

                emit('timeout');
            }, 4500);
        })();
    </script>
</body>

</html>
