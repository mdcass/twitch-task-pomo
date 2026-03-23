<?php

namespace App\Models;

use App\Enums\Models\WidgetType;
use Database\Factories\WidgetInstanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WidgetInstance extends Model
{
    /** @use HasFactory<WidgetInstanceFactory> */
    use HasFactory;

    protected $fillable = [
        'canvas_id',
        'team_id',
        'type',
        'name',
        'position_x',
        'position_y',
        'width',
        'height',
        'z_index',
        'is_visible',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'type' => WidgetType::class,
            'position_x' => 'integer',
            'position_y' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'z_index' => 'integer',
            'is_visible' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function canvas(): BelongsTo
    {
        return $this->belongsTo(Canvas::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
