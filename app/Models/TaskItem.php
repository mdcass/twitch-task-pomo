<?php

namespace App\Models;

use App\Enums\Models\TaskItemSource;
use App\Enums\Models\TaskItemStatus;
use Database\Factories\TaskItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskItem extends Model
{
    /** @use HasFactory<TaskItemFactory> */
    use HasFactory;

    protected $fillable = [
        'team_id',
        'stream_session_id',
        'created_by_user_id',
        'source',
        'submitted_by_username',
        'submitted_by_provider_user_id',
        'body',
        'status',
        'sort_order',
        'completed_at',
        'archived_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'source' => TaskItemSource::class,
            'status' => TaskItemStatus::class,
            'sort_order' => 'integer',
            'completed_at' => 'datetime',
            'archived_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function streamSession(): BelongsTo
    {
        return $this->belongsTo(StreamSession::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
