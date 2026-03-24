<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $canvas->name }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/phoenix/app.scss'])
</head>

<body class="overlay-runtime-page">
    <main class="overlay-runtime">
        <div class="overlay-runtime__surface" style="width: {{ $canvas->width }}px; height: {{ $canvas->height }}px;">
            @foreach ($canvas->orderedWidgetInstances as $widget)
                @continue(!$widget->is_visible)

                @php($widgetFrameUrl = $widgetFrameUrls[$widget->id] ?? null)
                @php($scaleX = number_format($widget->renderScaleX(), 6, '.', ''))
                @php($scaleY = number_format($widget->renderScaleY(), 6, '.', ''))
                @php($offsetX = number_format($widget->crop_left * $widget->renderScaleX(), 6, '.', ''))
                @php($offsetY = number_format($widget->crop_top * $widget->renderScaleY(), 6, '.', ''))
                @php($scaledContentWidth = number_format($widget->content_width * $widget->renderScaleX(), 6, '.', ''))
                @php($scaledContentHeight = number_format($widget->content_height * $widget->renderScaleY(), 6, '.', ''))
                @php($frameSandbox = $widget->source_kind === \App\Enums\Models\WidgetSourceKind::RemoteUrl ? 'allow-scripts allow-same-origin' : 'allow-scripts')

                <section class="overlay-runtime__widget"
                    style="left: {{ $widget->position_x }}px; top: {{ $widget->position_y }}px; width: {{ $widget->width }}px; height: {{ $widget->height }}px; z-index: {{ $widget->z_index }};">
                    @if (is_string($widgetFrameUrl) && $widgetFrameUrl !== '')
                        <div class="overlay-runtime__offset"
                            style="width: {{ $scaledContentWidth }}px; height: {{ $scaledContentHeight }}px; transform: translate(-{{ $offsetX }}px, -{{ $offsetY }}px);">
                            <div class="overlay-runtime__scale"
                                style="width: {{ $widget->content_width }}px; height: {{ $widget->content_height }}px; transform: scale({{ $scaleX }}, {{ $scaleY }});">
                                <iframe class="overlay-runtime__iframe" src="{{ $widgetFrameUrl }}" loading="lazy"
                                    sandbox="{{ $frameSandbox }}" referrerpolicy="strict-origin-when-cross-origin"
                                    allowtransparency="true"
                                    style="width: {{ $widget->content_width }}px; height: {{ $widget->content_height }}px;"></iframe>
                            </div>
                        </div>
                    @endif
                </section>
            @endforeach
        </div>
    </main>
</body>

</html>
