<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use App\Models\WorkflowStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Workflows\Stubs\DummyGuestWorkflowComponent;
use Tests\Feature\Workflows\Stubs\DummyWorkflowComponent;
use Tests\TestCase;

class LivewireWorkflowConcernTest extends TestCase
{
    use RefreshDatabase;

    public function test_livewire_workflow_concern_applies_transition_and_persists_store(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $this->actingAs($user);

        $component = Livewire::test(DummyWorkflowComponent::class);

        $this->assertTrue($component->instance()->workflow->isState('register'));
        $this->assertFalse($component->instance()->can('set_password'));

        $component->set('allowSetPassword', true)
            ->call('apply', 'set_password', ['source' => 'ui']);

        $this->assertTrue($component->instance()->workflow->isState('password'));

        $store = WorkflowStore::query()
            ->where('team_id', $user->current_team_id)
            ->where('workflow_class', DummyWorkflowComponent::class)
            ->first();

        $this->assertNotNull($store);
        $this->assertSame($user->id, $store->created_by_user_id);
        $this->assertSame($user::class, $store->subject_type);
        $this->assertSame($user->id, $store->subject_id);
        $this->assertSame('password', $store->records[0]['to'] ?? null);
        $this->assertSame('ui', $store->records[0]['context']['source'] ?? null);
    }

    public function test_livewire_workflow_concern_uses_component_fallback_guard(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $this->actingAs($user);

        $component = Livewire::test(DummyWorkflowComponent::class);

        $this->assertFalse($component->instance()->can('fallback_only_transition'));

        $component->set('allowFallback', true);

        $this->assertTrue($component->instance()->can('fallback_only_transition'));
    }

    public function test_guest_livewire_workflow_persists_to_session_backed_store(): void
    {
        $component = Livewire::test(DummyGuestWorkflowComponent::class);

        $component->set('allowSetPassword', true)
            ->call('apply', 'set_password', ['source' => 'guest-ui']);

        $workflowStoreId = session()->get('workflow_store_id.'.DummyGuestWorkflowComponent::class);

        $this->assertNotNull($workflowStoreId);

        $store = WorkflowStore::query()->find($workflowStoreId);

        $this->assertNotNull($store);
        $this->assertNull($store->team_id);
        $this->assertNull($store->created_by_user_id);
        $this->assertSame('guest-ui', $store->records[0]['context']['source'] ?? null);
    }

    public function test_guest_livewire_workflow_restores_from_session(): void
    {
        $firstMount = Livewire::test(DummyGuestWorkflowComponent::class);

        $firstMount->set('allowSetPassword', true)
            ->call('apply', 'set_password', ['source' => 'guest-ui']);

        $secondMount = Livewire::test(DummyGuestWorkflowComponent::class);

        $this->assertTrue($secondMount->instance()->workflow->isState('password'));
    }
}
