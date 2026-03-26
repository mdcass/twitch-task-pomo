<?php

namespace App\Support\Database;

use Illuminate\Database\Eloquent\Builder;

class JsonArrayContains
{
    /**
     * @param  list<string>  $values
     */
    public function whereContainsAll(Builder $query, string $column, array $values): Builder
    {
        if ($values === []) {
            return $query;
        }

        if ($query->getConnection()->getDriverName() !== 'sqlite') {
            foreach ($values as $value) {
                $query->whereJsonContains($column, $value);
            }

            return $query;
        }

        $wrappedColumn = $query->getQuery()->grammar->wrap($column);

        foreach ($values as $value) {
            $query->whereRaw(
                "exists (select 1 from json_each($wrappedColumn) where json_each.value = ?)",
                [$value],
            );
        }

        return $query;
    }
}
