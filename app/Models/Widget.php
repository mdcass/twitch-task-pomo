<?php

namespace App\Models;

use App\Enums\ActivityEvent;
use App\Enums\Models\WidgetLifecycleState;
use App\Enums\Models\WidgetType;
use App\Exceptions\DomainInvariantViolation;
use App\Models\Widgets\FollowerGoalState;
use App\Models\Traits\LogsModelActivity;
use App\Support\Widgets\WidgetDefinition;
use Database\Factories\WidgetFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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

    public function widgetInstances(): HasMany
    {
        return $this->hasMany(WidgetInstance::class);
    }

    public function followerGoalState(): HasOne
    {
        return $this->hasOne(FollowerGoalState::class);
    }

    public function definition(): WidgetDefinition
    {
        return $this->type->definition();
    }

    public function teamOrFail(): Team
    {
        $this->loadMissing('team');

        if ($this->team instanceof Team) {
            return $this->team;
        }

        throw DomainInvariantViolation::for('Widget must resolve an owning team.');
    }

    public function followerGoalStateOrFail(): FollowerGoalState
    {
        if ($this->type !== WidgetType::FollowerGoal) {
            throw DomainInvariantViolation::for('Only Follower Goal widgets may resolve follower-goal state.');
        }

        $this->loadMissing('followerGoalState');

        if ($this->followerGoalState instanceof FollowerGoalState) {
            return $this->followerGoalState;
        }

        throw DomainInvariantViolation::for('Follower Goal widgets must resolve follower-goal state.');
    }

    public function usageCount(): int
    {
        return $this->widgetInstances()->count();
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

    public function isReadyForRuntime(): bool
    {
        return $this->lifecycle_state === WidgetLifecycleState::Ready;
    }

    public function healthLabel(): string
    {
        return match ($this->lifecycle_state) {
            WidgetLifecycleState::Draft, WidgetLifecycleState::PendingConnection => 'Needs Setup',
            default => $this->lifecycle_state->label(),
        };
    }

    public function standaloneStatusLabel(): string
    {
        return $this->isPublished() ? 'Live' : 'Off';
    }

    public function bootstrapForCreation(): static
    {
        if ($this->type === WidgetType::FollowerGoal) {
            $this->followerGoalState()->firstOrCreate(
                ['widget_id' => $this->id],
                ['current_count' => 0],
            );
        }

        $this->lifecycle_state = $this->resolveActiveLifecycleState();
        $this->save();

        return $this;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $appearance
     */
    public function applyEditorUpdate(string $name, array $config, array $appearance): static
    {
        $this->fill([
            'name' => trim($name),
            'schema_version' => $this->definition()->schemaVersion(),
            'config' => $config,
            'appearance' => $appearance,
        ]);

        if ($this->lifecycle_state !== WidgetLifecycleState::Archived) {
            $this->lifecycle_state = $this->resolveLifecycleState();
        }

        $this->save();

        return $this;
    }

    public function publish(): static
    {
        if ($this->lifecycle_state === WidgetLifecycleState::Archived) {
            throw ValidationException::withMessages([
                'lifecycle_state' => ['Archived widgets cannot be published.'],
            ]);
        }

        if ($this->lifecycle_state === WidgetLifecycleState::Broken) {
            throw ValidationException::withMessages([
                'lifecycle_state' => ['Broken widgets cannot be published.'],
            ]);
        }

        $this->forceFill([
            'lifecycle_state' => $this->resolveActiveLifecycleState(),
            'published_at' => now(),
            'publication_key' => $this->publication_key ?: (string) Str::uuid(),
        ])->save();

        return $this;
    }

    public function unpublish(): static
    {
        $this->forceFill([
            'published_at' => null,
        ])->save();

        return $this;
    }

    public function regeneratePublicationKey(): static
    {
        $this->forceFill([
            'published_at' => now(),
            'publication_key' => (string) Str::uuid(),
        ])->save();

        return $this;
    }

    public function archive(): static
    {
        $this->forceFill([
            'lifecycle_state' => WidgetLifecycleState::Archived,
            'published_at' => null,
        ])->save();

        return $this;
    }

    public function restore(): static
    {
        $this->forceFill([
            'lifecycle_state' => $this->resolveActiveLifecycleState(),
        ])->save();

        return $this;
    }

    public function refreshLifecycle(): static
    {
        if ($this->lifecycle_state === WidgetLifecycleState::Archived) {
            return $this;
        }

        $this->forceFill([
            'lifecycle_state' => $this->resolveLifecycleState(),
        ])->save();

        return $this;
    }

    public function resetFollowerGoal(): static
    {
        if ($this->type !== WidgetType::FollowerGoal) {
            throw DomainInvariantViolation::for('Only Follower Goal widgets may reset follower-goal progress.');
        }

        $state = $this->followerGoalState;
        if (! $state instanceof FollowerGoalState) {
            throw DomainInvariantViolation::for('Follower Goal widgets must resolve follower-goal state.');
        }

        $state->resetProgress();
        $this->setRelation('followerGoalState', $state->fresh());

        return $this;
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

    private function resolveLifecycleState(): WidgetLifecycleState
    {
        if ($this->lifecycle_state === WidgetLifecycleState::Archived) {
            return WidgetLifecycleState::Archived;
        }

        return $this->resolveActiveLifecycleState();
    }

    private function resolveActiveLifecycleState(): WidgetLifecycleState
    {
        if ($this->lifecycle_state === WidgetLifecycleState::Broken) {
            return WidgetLifecycleState::Broken;
        }

        $definition = $this->definition();

        if (! $definition->requiresProviderConnection()) {
            return WidgetLifecycleState::Ready;
        }

        $requiredProvider = $definition->requiredProvider();

        $this->loadMissing('team');

        return $this->team->hasUsableProviderAuth($requiredProvider)
            ? WidgetLifecycleState::Ready
            : WidgetLifecycleState::PendingConnection;
    }
}
