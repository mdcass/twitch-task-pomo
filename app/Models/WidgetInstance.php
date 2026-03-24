<?php

namespace App\Models;

use App\Enums\Models\WidgetPreviewStatus;
use App\Enums\Models\WidgetSourceKind;
use App\Enums\Models\WidgetType;
use Database\Factories\WidgetInstanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WidgetInstance extends Model
{
    /** @use HasFactory<WidgetInstanceFactory> */
    use HasFactory;

    protected $fillable = [
        'canvas_id',
        'team_id',
        'source_kind',
        'type',
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
            'type' => WidgetType::class,
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

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function displayName(): string
    {
        if (filled($this->name)) {
            return $this->name;
        }

        if ($this->source_kind === WidgetSourceKind::BuiltIn && $this->type instanceof WidgetType) {
            return $this->type->defaultName();
        }

        if ($this->source_kind === WidgetSourceKind::RemoteUrl && filled($this->embed_url)) {
            $host = parse_url($this->embed_url, PHP_URL_HOST);

            if (is_string($host) && $host !== '') {
                return Str::headline($host);
            }
        }

        return 'Widget';
    }

    public function previewUrl(): ?string
    {
        if ($this->source_kind === WidgetSourceKind::RemoteUrl) {
            return $this->preview_status === WidgetPreviewStatus::Ready ? $this->embed_url : null;
        }

        return $this->type?->previewUrl($this->settings ?? []);
    }

    public function usesIframePreview(): bool
    {
        return is_string($this->previewUrl()) && $this->previewUrl() !== '';
    }

    public function hasPreviewFailure(): bool
    {
        return in_array($this->preview_status, [WidgetPreviewStatus::Blocked, WidgetPreviewStatus::Unknown], true);
    }

    public function visibleContentWidth(): int
    {
        return max(1, $this->content_width - $this->crop_left - $this->crop_right);
    }

    /**
     * @return array{frame_width:int, frame_height:int, content_width:int, content_height:int}
     */
    public function editorDefaults(): array
    {
        return [
            'frame_width' => max(120, (int) data_get($this->settings, 'editor_defaults.frame_width', $this->width)),
            'frame_height' => max(90, (int) data_get($this->settings, 'editor_defaults.frame_height', $this->height)),
            'content_width' => max(1, (int) data_get($this->settings, 'editor_defaults.content_width', $this->content_width)),
            'content_height' => max(1, (int) data_get($this->settings, 'editor_defaults.content_height', $this->content_height)),
        ];
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
}
