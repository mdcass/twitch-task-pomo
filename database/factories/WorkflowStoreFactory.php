<?php

namespace Database\Factories;

use App\Enums\Models\WorkflowStatus;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkflowStore;
use App\Workflows\ArrayWorkflow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowStore>
 */
class WorkflowStoreFactory extends Factory
{
    protected $model = WorkflowStore::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'created_by_user_id' => User::factory(),
            'subject_type' => null,
            'subject_id' => null,
            'workflow_class' => ArrayWorkflow::class,
            'status' => WorkflowStatus::OPEN,
            'records' => [],
        ];
    }
}
