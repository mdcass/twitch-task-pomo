<?php

namespace App\Models;

use App\Enums\Models\WidgetPreviewStatus;
use App\Enums\Models\WidgetSourceKind;
use App\Enums\Models\WidgetType;
use App\Exceptions\DomainInvariantViolation;
use App\Models\Concerns\EnforcesModelInvariants;
use Database\Factories\WidgetInstanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WidgetInstance extends Model
{
    /** @use HasFactory<WidgetInstanceFactory> */
    use HasFactory;
    use EnforcesModelInvariants;

    protected $table = 'canvas_widgets';

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
        if ($this->source_kind === WidgetSourceKind::Proprietary) {
            return $this->proprietaryWidget()->displayName();
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
                ? $this->proprietaryWidget()->type
                : null,
        );
    }

    protected function enforceModelInvariants(): void
    {
        $canvasTeamId = $this->resolveCanvasTeamId();

        if ($canvasTeamId !== $this->team_id) {
            throw DomainInvariantViolation::for('Widget instance team_id must match its parent canvas team.');
        }

        if ($this->source_kind === WidgetSourceKind::Proprietary) {
            $widget = $this->resolveBackingWidget();

            if (! $widget instanceof Widget) {
                throw DomainInvariantViolation::for('Proprietary widget instances must reference a backing widget.');
            }

            if ($widget->team_id !== $this->team_id) {
                throw DomainInvariantViolation::for('Proprietary widget instances must reference a widget from the same team.');
            }

            return;
        }

        if ($this->widget_id !== null) {
            throw DomainInvariantViolation::for('Remote widget instances may not reference a proprietary widget.');
        }
    }

    private function proprietaryWidget(): Widget
    {
        $widget = $this->resolveBackingWidget();

        if ($widget instanceof Widget) {
            return $widget;
        }

        throw DomainInvariantViolation::for('Proprietary widget instances must resolve a backing widget.');
    }

    private function resolveBackingWidget(): ?Widget
    {
        if ($this->widget_id === null) {
            return null;
        }

        if ($this->relationLoaded('widget')) {
            $relation = $this->getRelation('widget');

            return $relation instanceof Widget ? $relation : null;
        }

        return Widget::query()->find($this->widget_id);
    }

    private function resolveCanvasTeamId(): int
    {
        if ($this->relationLoaded('canvas')) {
            $canvas = $this->getRelation('canvas');

            if ($canvas instanceof Canvas) {
                return (int) $canvas->team_id;
            }
        }

        return (int) Canvas::query()->findOrFail($this->canvas_id)->team_id;
    }
}
