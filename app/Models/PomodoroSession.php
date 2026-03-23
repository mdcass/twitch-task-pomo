<?php

namespace App\Models;

use App\Enums\Models\PomodoroSessionState;
use Database\Factories\PomodoroSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PomodoroSession extends Model
{
    /** @use HasFactory<PomodoroSessionFactory> */
    use HasFactory;

    protected $fillable = [
        'team_id',
        'stream_session_id',
        'state',
        'focus_minutes',
        'break_minutes',
        'started_at',
        'ends_at',
        'paused_at',
        'sequence',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'state' => PomodoroSessionState::class,
            'focus_minutes' => 'integer',
            'break_minutes' => 'integer',
            'started_at' => 'datetime',
            'ends_at' => 'datetime',
            'paused_at' => 'datetime',
            'sequence' => 'integer',
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
}
