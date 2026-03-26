<?php

namespace App\Models;

use App\Enums\ActivityEvent;
use App\Enums\Models\WidgetLifecycleState;
use App\Enums\Models\WidgetType;
use App\Models\Traits\LogsModelActivity;
use Database\Factories\WidgetFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Widget extends Model
{
    /** @use HasFactory<WidgetFactory> */
    use HasFactory;
    use HasUuids;
    use LogsModelActivity;

    protected static array $recordEvents = [
        'created',
        'updated',
    ];

    protected $fillable = [
        'uuid',
        'team_id',
        'created_by_user_id',
        'type',
        'name',
        'schema_version',
        'config',
        'appearance',
        'lifecycle_state',
        'published_at',
        'publication_key',
    ];

    protected function casts(): array
    {
        return [
            'type' => WidgetType::class,
            'schema_version' => 'integer',
            'config' => 'array',
            'appearance' => 'array',
            'lifecycle_state' => WidgetLifecycleState::class,
            'published_at' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function canvasWidgets(): HasMany
    {
        return $this->hasMany(CanvasWidget::class);
    }

    public function followerGoalState(): HasOne
    {
        return $this->hasOne(FollowerGoalState::class);
    }

    public function usageCount(): int
    {
        return $this->canvasWidgets()->count();
    }

    public function displayName(): string
    {
        return filled($this->name)
            ? trim((string) $this->name)
            : $this->type->defaultName();
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && filled($this->publication_key);
    }

    protected function activityEventMap(): array
    {
        return [
            'created' => ActivityEvent::WidgetCreated->value,
            'updated' => ActivityEvent::WidgetUpdated->value,
        ];
    }

    protected function activityLogAttributes(): array
    {
        return [
            'team_id',
            'created_by_user_id',
            'type',
            'name',
            'schema_version',
            'lifecycle_state',
            'published_at',
        ];
    }

    protected function activityLogName(): string
    {
        return 'widget';
    }
}
