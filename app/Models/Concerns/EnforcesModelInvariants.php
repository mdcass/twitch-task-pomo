<?php

namespace App\Models\Concerns;

trait EnforcesModelInvariants
{
    public static function bootEnforcesModelInvariants(): void
    {
        static::saving(function (self $model): void {
            $model->enforceModelInvariants();
        });

        static::deleting(function (self $model): void {
            $model->enforceDeletionInvariants();
        });
    }

    protected function enforceModelInvariants(): void
    {
        //
    }

    protected function enforceDeletionInvariants(): void
    {
        //
    }
}
