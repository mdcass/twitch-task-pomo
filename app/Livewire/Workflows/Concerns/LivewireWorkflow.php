<?php

namespace App\Livewire\Workflows\Concerns;

use App\Enums\Models\WorkflowStatus;
use App\Models\WorkflowStore;
use App\Workflows\ArrayWorkflow;
use App\Workflows\GuardResult;

trait LivewireWorkflow
{
    public ArrayWorkflow $workflow;

    public function mountWorkflowComponent(): void
    {
        $this->workflow ??= $this->restoreWorkflowFromStore() ?? new ArrayWorkflow(
            class: static::class,
            subject: null,
            status: WorkflowStatus::OPEN,
            records: [],
            places: $this->places(),
            transitions: $this->transitions(),
        );
    }

    public function apply(string $transition, array $context = []): void
    {
        try {
            $guardResult = $this->guardFor($transition);
            $this->workflow->apply($transition, $context, $guardResult);
        } finally {
            $this->saveWorkflow();
        }
    }

    public function can(string $transition): bool
    {
        return $this->guardFor($transition)->passes();
    }

    protected function guardFor(string $transition): GuardResult
    {
        $callback = $this->workflow->runGuardMethodsCallback($transition)->bindTo($this, $this);

        return $this->workflow->guardFor($transition, $callback());
    }

    protected function saveWorkflow(): void
    {
        $this->workflow->saveStore(useSession: $this->workflowUsesSession());
    }

    protected function workflowUsesSession(): bool
    {
        return false;
    }

    protected function restoreWorkflowFromStore(): ?ArrayWorkflow
    {
        $workflowId = session()->get('workflow_store_id.'.static::class);

        if (! is_int($workflowId) && ! ctype_digit((string) $workflowId)) {
            return null;
        }

        $store = WorkflowStore::query()->find((int) $workflowId);

        if (! $store instanceof WorkflowStore || $store->workflow_class !== static::class) {
            return null;
        }

        return ArrayWorkflow::fromStore(
            store: $store,
            places: $this->places(),
            transitions: $this->transitions(),
        );
    }
}
