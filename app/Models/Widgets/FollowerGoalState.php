<?php

namespace App\Models\Widgets;

use App\Models\Widget;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FollowerGoalState extends Model
{
    use HasFactory;

    protected $table = 'widget_follower_goal_states';

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

    public function resetProgress(): static
    {
        $this->forceFill([
            'current_count' => 0,
            'frozen_at' => null,
            'last_followed_at' => null,
        ])->save();

        return $this;
    }
}
