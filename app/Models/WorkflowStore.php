<?php

namespace App\Models;

use App\Enums\Models\WorkflowStatus;
use App\Workflows\BaseWorkflow;
use Database\Factories\WorkflowStoreFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WorkflowStore extends Model
{
    /** @use HasFactory<WorkflowStoreFactory> */
    use HasFactory;

    protected $fillable = [
        'team_id',
        'created_by_user_id',
        'subject_type',
        'subject_id',
        'workflow_class',
        'status',
        'records',
    ];

    protected function casts(): array
    {
        return [
            'records' => 'array',
            'status' => WorkflowStatus::class,
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeClosed($query)
    {
        return $query->where('status', WorkflowStatus::CLOSED);
    }

    public function scopeError($query)
    {
        return $query->where('status', WorkflowStatus::ERROR);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', WorkflowStatus::OPEN);
    }

    public function workflow(): BaseWorkflow
    {
        return $this->workflow_class::fromStore($this);
    }
}
