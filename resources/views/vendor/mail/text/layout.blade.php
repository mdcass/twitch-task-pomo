{!! \App\Support\Mail\MarkdownSlot::toText($header ?? '') !!}

{!! \App\Support\Mail\MarkdownSlot::toText($slot) !!}
@isset($subcopy)
    {!! \App\Support\Mail\MarkdownSlot::toText($subcopy) !!}
@endisset

{!! \App\Support\Mail\MarkdownSlot::toText($footer ?? '') !!}
