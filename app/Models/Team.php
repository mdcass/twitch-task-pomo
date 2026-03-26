<?php

namespace App\Models;

use App\Enums\ActivityEvent;
use App\Enums\TeamType;
use App\Models\Traits\LogsModelActivity;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Jetstream\Events\TeamCreated;
use Laravel\Jetstream\Events\TeamDeleted;
use Laravel\Jetstream\Events\TeamUpdated;
use Laravel\Jetstream\Team as JetstreamTeam;

class Team extends JetstreamTeam
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    use LogsModelActivity;
    use SoftDeletes;

    protected static array $recordEvents = [
        'created',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'type',
    ];

    /**
     * The event map for the model.
     *
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'created' => TeamCreated::class,
        'updated' => TeamUpdated::class,
        'deleted' => TeamDeleted::class,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
            'type' => TeamType::class,
        ];
    }

    /**
     * Get the workflow stores owned by the team.
     *
     * @return HasMany<WorkflowStore, $this>
     */
    public function workflowStores(): HasMany
    {
        return $this->hasMany(WorkflowStore::class);
    }

    /**
     * Get the owner user for the team.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the streams owned by the team.
     *
     * @return HasMany<Stream, $this>
     */
    public function streams(): HasMany
    {
        return $this->hasMany(Stream::class);
    }

    /**
     * Get the stream sessions owned by the team.
     *
     * @return HasMany<StreamSession, $this>
     */
    public function streamSessions(): HasMany
    {
        return $this->hasMany(StreamSession::class);
    }

    /**
     * Get the canvases owned by the team.
     *
     * @return HasMany<Canvas, $this>
     */
    public function canvases(): HasMany
    {
        return $this->hasMany(Canvas::class);
    }

    /**
     * Get the reusable widgets owned by the team.
     *
     * @return HasMany<Widget, $this>
     */
    public function widgets(): HasMany
    {
        return $this->hasMany(Widget::class);
    }

    /**
     * Get the canvas widget placements owned by the team.
     *
     * @return HasMany<CanvasWidget, $this>
     */
    public function canvasWidgets(): HasMany
    {
        return $this->hasMany(CanvasWidget::class);
    }

    /**
     * Get the widget instances owned by the team.
     *
     * @return HasMany<WidgetInstance, $this>
     */
    public function widgetInstances(): HasMany
    {
        return $this->hasMany(WidgetInstance::class, 'team_id');
    }

    /**
     * Get the task items owned by the team.
     *
     * @return HasMany<TaskItem, $this>
     */
    public function taskItems(): HasMany
    {
        return $this->hasMany(TaskItem::class);
    }

    /**
     * Get the pomodoro sessions owned by the team.
     *
     * @return HasMany<PomodoroSession, $this>
     */
    public function pomodoroSessions(): HasMany
    {
        return $this->hasMany(PomodoroSession::class);
    }

    protected function activityEventMap(): array
    {
        return [
            'created' => ActivityEvent::TeamCreated->value,
        ];
    }

    protected function activityLogAttributes(): array
    {
        return [
            'user_id',
            'name',
            'type',
        ];
    }
}
