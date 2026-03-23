<?php

namespace App\Models;

use App\Enums\ActivityEvent;
use App\Enums\TeamType;
use App\Models\Traits\LogsModelActivity;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
