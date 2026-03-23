<?php

namespace App\Models\Traits;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

trait LogsModelActivity
{
    use LogsActivity;

    abstract protected function activityEventMap(): array;

    abstract protected function activityLogAttributes(): array;

    protected function activityLogName(): string
    {
        return 'auth';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName($this->activityLogName())
            ->logOnly($this->activityLogAttributes())
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName): string => $this->activityEventMap()[$eventName] ?? $eventName);
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        $mappedEvent = $this->activityEventMap()[$eventName] ?? null;

        if ($mappedEvent === null) {
            return;
        }

        $activity->event = $mappedEvent;
        $activity->description = $mappedEvent;
    }
}
