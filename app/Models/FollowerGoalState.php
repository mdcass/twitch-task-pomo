<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FollowerGoalState extends Model
{
    use HasFactory;

    protected $fillable = [
        'widget_id',
        'current_count',
        'frozen_at',
        'last_followed_at',
    ];

    protected function casts(): array
    {
        return [
            'current_count' => 'integer',
            'frozen_at' => 'datetime',
            'last_followed_at' => 'datetime',
        ];
    }

    public function widget(): BelongsTo
    {
        return $this->belongsTo(Widget::class);
    }
}
