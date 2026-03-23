<?php

namespace App\Models;

use App\Enums\ActivityEvent;
use App\Models\Traits\LogsModelActivity;
use Database\Factories\CanvasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Canvas extends Model
{
    /** @use HasFactory<CanvasFactory> */
    use HasFactory;
    use HasUuids;
    use LogsModelActivity;
    use SoftDeletes;

    protected static array $recordEvents = [
        'created',
        'updated',
        'deleted',
        'restored',
    ];

    protected $fillable = [
        'team_id',
        'created_by_user_id',
        'uuid',
        'name',
        'width',
        'height',
    ];

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'deleted_at' => 'datetime',
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

    public function widgetInstances(): HasMany
    {
        return $this->hasMany(WidgetInstance::class);
    }

    protected function activityEventMap(): array
    {
        return [
            'created' => ActivityEvent::CanvasCreated->value,
            'updated' => ActivityEvent::CanvasUpdated->value,
            'deleted' => ActivityEvent::CanvasArchived->value,
            'restored' => ActivityEvent::CanvasRestored->value,
        ];
    }

    protected function activityLogAttributes(): array
    {
        return [
            'team_id',
            'created_by_user_id',
            'name',
            'width',
            'height',
            'deleted_at',
        ];
    }

    protected function activityLogName(): string
    {
        return 'canvas';
    }
}
