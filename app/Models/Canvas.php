<?php

namespace App\Models;

use Database\Factories\CanvasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Canvas extends Model
{
    /** @use HasFactory<CanvasFactory> */
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'team_id',
        'created_by_user_id',
        'uuid',
        'name',
        'width',
        'height',
    ];

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
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
}
