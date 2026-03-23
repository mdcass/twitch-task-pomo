<?php

namespace Tests\Feature\Workflows;

use App\Enums\Models\WorkflowStatus;
use App\Workflows\ArrayWorkflow;
use Tests\Feature\Workflows\Stubs\DummyWorkflow;
use Tests\TestCase;

class ArrayWorkflowTest extends TestCase
{
    public function test_array_workflow_serializes_and_restores_with_encrypted_livewire_payload(): void
    {
        $workflow = new ArrayWorkflow(
            class: DummyWorkflow::class,
            subject: null,
            status: WorkflowStatus::OPEN,
            records: [
                [
                    'from' => 'register',
                    'to' => 'password',
                    'context' => ['source' => 'test'],
                    'failed' => false,
                    'timestamp' => now()->toDateTimeString(),
                ],
            ],
            places: ['register', 'password', 'complete'],
            transitions: [
                'set_password' => [
                    'from' => 'register',
                    'to' => 'password',
                ],
            ],
        );

        $payload = $workflow->toLivewire();
        $plainJson = json_encode($workflow->toArray(), JSON_THROW_ON_ERROR);

        $this->assertIsArray($payload);
        $this->assertIsString($payload['payload'] ?? null);
        $this->assertStringNotContainsString($plainJson, $payload['payload']);

        $restored = ArrayWorkflow::fromLivewire($payload);

        $this->assertSame(DummyWorkflow::class, $restored->toArray()['workflow_class']);
        $this->assertSame('password', $restored->getState());
        $this->assertSame('test', $restored->getContextValue('set_password', 'source'));
    }
}
