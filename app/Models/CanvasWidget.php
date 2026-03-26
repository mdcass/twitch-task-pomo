<?php

namespace App\Models;

use App\Enums\Models\WidgetPreviewStatus;
use App\Enums\Models\WidgetSourceKind;
use App\Enums\Models\WidgetType;
use Database\Factories\CanvasWidgetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CanvasWidget extends Model
{
    /** @use HasFactory<CanvasWidgetFactory> */
    use HasFactory;

    protected $fillable = [
        'canvas_id',
        'widget_id',
        'team_id',
        'source_kind',
        'name',
        'embed_url',
        'position_x',
        'position_y',
        'width',
        'height',
        'content_width',
        'content_height',
        'crop_top',
        'crop_right',
        'crop_bottom',
        'crop_left',
        'z_index',
        'is_visible',
        'settings',
        'preview_status',
        'preview_message',
        'preview_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'source_kind' => WidgetSourceKind::class,
            'position_x' => 'integer',
            'position_y' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'content_width' => 'integer',
            'content_height' => 'integer',
            'crop_top' => 'integer',
            'crop_right' => 'integer',
            'crop_bottom' => 'integer',
            'crop_left' => 'integer',
            'z_index' => 'integer',
            'is_visible' => 'boolean',
            'settings' => 'array',
            'preview_status' => WidgetPreviewStatus::class,
            'preview_checked_at' => 'datetime',
        ];
    }

    public function canvas(): BelongsTo
    {
        return $this->belongsTo(Canvas::class);
    }

    public function widget(): BelongsTo
    {
        return $this->belongsTo(Widget::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function displayName(): string
    {
        if ($this->source_kind === WidgetSourceKind::Proprietary && $this->widget !== null) {
            return $this->widget->displayName();
        }

        if (filled($this->name)) {
            return trim((string) $this->name);
        }

        if (filled($this->embed_url)) {
            $host = parse_url((string) $this->embed_url, PHP_URL_HOST);

            if (is_string($host) && $host !== '') {
                return Str::headline($host);
            }
        }

        return 'Widget';
    }

    public function previewUrl(): ?string
    {
        return $this->source_kind === WidgetSourceKind::RemoteUrl
            && $this->preview_status === WidgetPreviewStatus::Ready
            ? $this->embed_url
            : null;
    }

    public function usesIframePreview(): bool
    {
        return is_string($this->previewUrl()) && $this->previewUrl() !== '';
    }

    public function hasPreviewFailure(): bool
    {
        return in_array($this->preview_status, [WidgetPreviewStatus::Blocked, WidgetPreviewStatus::Unknown], true);
    }

    public function editorDefaults(): array
    {
        return [
            'frame_width' => max(120, (int) data_get($this->settings, 'editor_defaults.frame_width', $this->width)),
            'frame_height' => max(90, (int) data_get($this->settings, 'editor_defaults.frame_height', $this->height)),
            'content_width' => max(1, (int) data_get($this->settings, 'editor_defaults.content_width', $this->content_width)),
            'content_height' => max(1, (int) data_get($this->settings, 'editor_defaults.content_height', $this->content_height)),
        ];
    }

    public function visibleContentWidth(): int
    {
        return max(1, $this->content_width - $this->crop_left - $this->crop_right);
    }

    public function visibleContentHeight(): int
    {
        return max(1, $this->content_height - $this->crop_top - $this->crop_bottom);
    }

    public function renderScaleX(): float
    {
        return $this->width / $this->visibleContentWidth();
    }

    public function renderScaleY(): float
    {
        return $this->height / $this->visibleContentHeight();
    }

    protected function type(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::get(
            fn (): ?WidgetType => $this->source_kind === WidgetSourceKind::Proprietary
                ? $this->widget?->type
                : null,
        );
    }
}
