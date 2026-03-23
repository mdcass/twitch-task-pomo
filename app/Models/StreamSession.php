<?php

namespace App\Models;

use App\Enums\Models\StreamSessionStatus;
use Database\Factories\StreamSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StreamSession extends Model
{
    /** @use HasFactory<StreamSessionFactory> */
    use HasFactory;

    protected $fillable = [
        'team_id',
        'stream_id',
        'status',
        'is_test',
        'started_at',
        'ended_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => StreamSessionStatus::class,
            'is_test' => 'boolean',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function stream(): BelongsTo
    {
        return $this->belongsTo(Stream::class);
    }

    public function taskItems(): HasMany
    {
        return $this->hasMany(TaskItem::class);
    }

    public function pomodoroSessions(): HasMany
    {
        return $this->hasMany(PomodoroSession::class);
    }
}
