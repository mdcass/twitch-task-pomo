<table class="panel" width="100%" cellpadding="0" cellspacing="0" role="presentation">
    <tr>
        <td class="panel-content">
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
                <tr>
                    <td class="panel-item">
                        {{ \App\Support\Mail\MarkdownSlot::toHtml($slot) }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
