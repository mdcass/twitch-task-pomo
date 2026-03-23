<?php

namespace App\Models;

use App\Enums\ActivityEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use Spatie\Activitylog\Facades\CauserResolver;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

class Activity extends SpatieActivity
{
    protected $casts = [
        'properties' => 'collection',
        'team_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $activity): void {
            $activity->properties = collect($activity->properties instanceof Collection
                ? $activity->properties->toArray()
                : (array) $activity->properties);
            $activity->properties = collect(self::scrubProperties($activity->properties->toArray()));

            foreach (self::resolveRequestContext() as $key => $value) {
                if ($value === null || $activity->properties->has($key)) {
                    continue;
                }

                $activity->properties = $activity->properties->put($key, $value);
            }

            if (is_int($activity->team_id)) {
                return;
            }

            $activity->team_id = self::resolveTeamIdFromTarget($activity->subject)
                ?? self::resolveTeamIdFromTarget($activity->causer)
                ?? self::normalizeTeamId($activity->team_id);
        });
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public static function log(
        ActivityEvent $event,
        array $properties = [],
        ?Model $subject = null,
        ?Model $causer = null,
        ?int $teamId = null,
        ?string $logName = 'auth',
    ): ?self {
        $logger = activity()
            ->useLog($logName)
            ->event($event->value)
            ->withProperties($properties);

        if ($subject !== null) {
            $logger->performedOn($subject);
        }

        if ($causer !== null) {
            $logger->causedBy($causer);
        }

        return $logger
            ->tap(function (self $activity) use ($subject, $causer, $teamId): void {
                $activity->team_id = self::resolveTeamIdFromTarget($subject)
                    ?? self::resolveTeamIdFromTarget($causer)
                    ?? self::normalizeTeamId($teamId);
            })
            ->log($event->value);
    }

    public static function withCauser(?Model $causer, callable $callback): mixed
    {
        CauserResolver::setCauser($causer);

        try {
            return $callback();
        } finally {
            CauserResolver::setCauser(null);
        }
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where('team_id', $teamId);
    }

    public static function resolveTeamIdFromTarget(?Model $target): ?int
    {
        if ($target === null) {
            return null;
        }

        if ($target instanceof Team) {
            return self::normalizeTeamId($target->getKey());
        }

        $teamId = self::normalizeTeamId($target->getAttribute('team_id'))
            ?? self::normalizeTeamId($target->getAttribute('current_team_id'));

        if ($teamId !== null) {
            return $teamId;
        }

        $team = self::resolveRelatedModel($target, 'team');

        if ($team instanceof Team) {
            return self::normalizeTeamId($team->getKey());
        }

        $user = self::resolveRelatedModel($target, 'user');

        if ($user instanceof User) {
            return self::normalizeTeamId($user->current_team_id)
                ?? self::normalizeTeamId($user->currentTeam?->getKey());
        }

        return null;
    }

    private static function resolveRelatedModel(Model $target, string $relation): ?Model
    {
        if (! method_exists($target, $relation)) {
            return null;
        }

        $related = $target->relationLoaded($relation)
            ? $target->getRelation($relation)
            : $target->{$relation};

        return $related instanceof Model ? $related : null;
    }

    private static function normalizeTeamId(mixed $teamId): ?int
    {
        if (is_int($teamId)) {
            return $teamId;
        }

        if (is_numeric($teamId)) {
            return (int) $teamId;
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private static function resolveRequestContext(): array
    {
        $context = [];
        $requestPath = request()?->path();

        if (is_string($requestPath) && $requestPath !== '') {
            $context['request_path'] = $requestPath;
        }

        if (! self::isLivewireRequest()) {
            return $context;
        }

        $originalPath = Livewire::originalPath();

        if (is_string($originalPath) && $originalPath !== '') {
            $context['request_path'] = $originalPath;
        }

        $componentName = Livewire::current()?->getName() ?? self::livewireSnapshotValue('memo.name');
        $methodName = request()->input('components.0.calls.0.method');

        if (is_string($componentName) && $componentName !== '') {
            $context['livewire_component'] = $componentName;
        }

        if (is_string($methodName) && $methodName !== '') {
            $context['livewire_method'] = $methodName;
        }

        return $context;
    }

    private static function isLivewireRequest(): bool
    {
        return app()->bound('livewire') && Livewire::isLivewireRequest();
    }

    private static function livewireSnapshotValue(string $path): mixed
    {
        $snapshot = request()->input('components.0.snapshot');

        if (! is_string($snapshot) || $snapshot === '') {
            return null;
        }

        $decodedSnapshot = json_decode($snapshot, true);

        if (! is_array($decodedSnapshot)) {
            return null;
        }

        return data_get($decodedSnapshot, $path);
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    private static function scrubProperties(array $properties): array
    {
        $scrubbed = [];
        $deniedKeys = [
            'access_token',
            'refresh_token',
            'token',
            'profile',
            'raw_profile',
        ];

        foreach ($properties as $key => $value) {
            if (in_array((string) $key, $deniedKeys, true)) {
                continue;
            }

            if (is_array($value)) {
                $scrubbed[$key] = self::scrubProperties($value);

                continue;
            }

            $scrubbed[$key] = $value;
        }

        return $scrubbed;
    }
}
