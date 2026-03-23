<?php

namespace Tests\Feature\Workflows;

use App\Enums\Models\WorkflowStatus;
use App\Exceptions\WorkflowTransitionException;
use App\Models\User;
use App\Models\WorkflowStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\Feature\Workflows\Stubs\DummyWorkflow;
use Tests\TestCase;

class BaseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_returns_workflow_and_transition_can_progress(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $this->actingAs($user);

        $workflow = app(DummyWorkflow::class)->setSubject($user);

        $this->assertTrue($workflow->isState('register'));
        $this->assertTrue($workflow->can('set_password'));
        $this->assertFalse($workflow->can('verify_email_address'));

        $workflow->apply('set_password');

        $this->assertTrue($workflow->isState('password'));
        $this->assertTrue($workflow->can('verify_email_address'));
    }

    public function test_resolve_from_session(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $this->actingAs($user);

        $workflow = app(DummyWorkflow::class)
            ->setSubject($user)
            ->saveStore(useSession: true);

        $this->assertNotNull($workflow->getStore());

        $workflowId = $workflow->getStore()?->id;

        $this->assertSame(
            $workflowId,
            session()->get('workflow_store_id.'.DummyWorkflow::class),
        );

        $foundWorkflow = DummyWorkflow::fromSession();

        $this->assertNotNull($foundWorkflow);
        $this->assertSame($workflowId, $foundWorkflow->getStore()?->id);
    }

    public function test_can_persist_session_backed_workflow_without_authenticated_user(): void
    {
        $workflow = app(DummyWorkflow::class)->saveStore(useSession: true);

        $store = $workflow->getStore();

        $this->assertNotNull($store);
        $this->assertNull($store?->team_id);
        $this->assertNull($store?->created_by_user_id);
        $this->assertSame($store?->id, session()->get('workflow_store_id.'.DummyWorkflow::class));

        $foundWorkflow = DummyWorkflow::fromSession();

        $this->assertNotNull($foundWorkflow);
        $this->assertSame($store?->id, $foundWorkflow->getStore()?->id);
    }

    public function test_throws_when_subject_backed_workflow_is_persisted_without_authentication(): void
    {
        $subject = User::factory()->withStreamerTeam()->create();
        $workflow = app(DummyWorkflow::class)->setSubject($subject);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Authenticated user required to persist a subject-backed workflow.');

        $workflow->saveStore();
    }

    public function test_anonymous_session_workflow_can_be_claimed_after_authentication(): void
    {
        $anonymousWorkflow = app(DummyWorkflow::class)->saveStore(useSession: true);
        $anonymousStoreId = $anonymousWorkflow->getStore()?->id;

        $user = User::factory()->withStreamerTeam()->create();
        $this->actingAs($user);

        $claimedWorkflow = DummyWorkflow::fromSession();

        $this->assertNotNull($claimedWorkflow);
        $this->assertSame($anonymousStoreId, $claimedWorkflow->getStore()?->id);

        $claimedWorkflow
            ->setSubject($user)
            ->saveStore();

        $refreshed = WorkflowStore::query()->findOrFail($anonymousStoreId);

        $this->assertSame($user->current_team_id, $refreshed->team_id);
        $this->assertSame($user->id, $refreshed->created_by_user_id);
        $this->assertSame($user::class, $refreshed->subject_type);
        $this->assertSame($user->id, $refreshed->subject_id);
    }

    public function test_associate_workflow_with_subject_and_from_subject_is_team_scoped(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $otherUser = User::factory()->withStreamerTeam()->create();
        $this->actingAs($user);

        $workflow = app(DummyWorkflow::class)->saveStore(useSession: true);

        $workflow->setSubject($user);
        $workflow->saveStore();

        $retrieved = DummyWorkflow::fromSubject($user, $user);

        $this->assertNotNull($retrieved);
        $this->assertSame($workflow->getStore()?->id, $retrieved->getStore()?->id);

        $crossTeamLookup = DummyWorkflow::fromSubject($otherUser, $user);

        $this->assertNull($crossTeamLookup);
    }

    public function test_workflow_transition_with_context_records_data(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $this->actingAs($user);

        $workflow = app(DummyWorkflow::class)->setSubject($user);

        $context = [
            'messages' => ['Profile completed successfully!'],
            'metadata' => [
                'completed_by' => 'user_123',
                'completed_at' => now()->toDateTimeString(),
            ],
        ];

        $workflow->apply('set_password', $context);
        $workflow->saveStore();

        $store = WorkflowStore::query()->findOrFail($workflow->getStore()?->id);

        $this->assertNotEmpty($store->records);
        $this->assertSame($context, $store->records[0]['context']);
        $this->assertSame('register', $store->records[0]['from']);
        $this->assertSame('password', $store->records[0]['to']);
        $this->assertSame($user->current_team_id, $store->team_id);
        $this->assertSame($user->id, $store->created_by_user_id);
    }

    public function test_workflow_status_defaults_open_and_can_close(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $this->actingAs($user);

        $openWorkflow = app(DummyWorkflow::class)
            ->setSubject($user)
            ->saveStore();

        $this->assertSame(WorkflowStatus::OPEN, $openWorkflow->getStore()?->status);

        $closedWorkflow = app(DummyWorkflow::class)
            ->setSubject($user)
            ->close()
            ->saveStore();

        $this->assertSame(WorkflowStatus::CLOSED, $closedWorkflow->getStore()?->status);
    }

    public function test_workflow_status_tracks_error_places(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $this->actingAs($user);

        /** @var DummyWorkflow $workflow */
        $workflow = app(DummyWorkflow::class)->setSubject($user);
        $workflow->errorPlaces = ['password'];

        $workflow->apply('set_password');

        $this->assertTrue($workflow->isState('password'));
        $this->assertSame(WorkflowStatus::ERROR, $workflow->getStatus());

        $workflow->apply('verify_email_address');

        $this->assertTrue($workflow->isState('complete'));
        $this->assertSame(WorkflowStatus::OPEN, $workflow->getStatus());
    }

    public function test_transition_helpers_return_context_and_history_data(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $this->actingAs($user);

        $workflow = app(DummyWorkflow::class)->setSubject($user);

        $workflow->apply('set_password', [
            'metadata' => [
                'completed_by' => 'user_123',
            ],
            'messages' => ['ok'],
        ]);

        $this->assertTrue($workflow->hasTransitionOccurred('set_password'));
        $this->assertFalse($workflow->hasTransitionOccurred('verify_email_address'));

        $lastTransition = $workflow->getLastTransition('set_password');

        $this->assertSame('register', $lastTransition['from']);
        $this->assertSame('password', $lastTransition['to']);
        $this->assertSame('user_123', $workflow->getContextValue('set_password', 'metadata.completed_by'));
        $this->assertSame('ok', $workflow->getContextValue('set_password', 'messages.0'));
        $this->assertSame('default', $workflow->getContextValue('set_password', 'missing', 'default'));
    }

    public function test_initial_context_helpers_read_first_successful_transition_context(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $this->actingAs($user);

        $workflow = app(DummyWorkflow::class)->setSubject($user);

        $workflow->apply('set_password', [
            'metadata' => [
                'completed_by' => 'user_123',
            ],
            'messages' => ['ok'],
        ]);

        $workflow->apply('verify_email_address', [
            'metadata' => [
                'completed_by' => 'user_456',
            ],
            'messages' => ['done'],
        ]);

        $this->assertSame('user_123', $workflow->getInitialContextValue('metadata.completed_by'));
        $this->assertSame('ok', $workflow->getInitialContextValue('messages.0'));
        $this->assertSame('default', $workflow->getInitialContextValue('missing', 'default'));
    }

    public function test_failed_transitions_are_recorded_and_exception_is_thrown(): void
    {
        $user = User::factory()->withStreamerTeam()->unverified()->create();
        $this->actingAs($user);

        $workflow = app(DummyWorkflow::class)->setSubject($user);

        $workflow->apply('set_password');

        try {
            $workflow->apply('verify_email_address');
            $this->fail('Expected WorkflowTransitionException was not thrown.');
        } catch (WorkflowTransitionException $exception) {
            $this->assertSame('verify_email_address', $exception->getTransition());
            $this->assertSame('Email must be verified before completing workflow.', $exception->getGuardResult()->message);
        }

        $failed = $workflow->getFailedTransitions();

        $this->assertCount(1, $failed);
        $this->assertTrue($workflow->hasTransitionFailed('verify_email_address'));
        $this->assertFalse($workflow->hasTransitionFailed('set_password'));

        $lastFailed = $workflow->getLastFailedTransition('verify_email_address');

        $this->assertNotNull($lastFailed);
        $this->assertTrue($lastFailed['failed']);
        $this->assertSame('failed', $lastFailed['context']['status']);
        $this->assertSame('Email must be verified before completing workflow.', $lastFailed['context']['error_message']);
    }

    public function test_fallback_guard_method_is_used_when_no_explicit_or_inferred_guard_exists(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $this->actingAs($user);

        $workflow = app(DummyWorkflow::class)->setSubject($user);

        $this->assertFalse($workflow->can('fallback_only_transition'));

        $this->expectException(WorkflowTransitionException::class);
        $this->expectExceptionMessage('Blocked by fallback guard');

        $workflow->apply('fallback_only_transition');
    }

    public function test_logs_error_when_transition_from_state_is_invalid(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $this->actingAs($user);

        Log::spy();

        $workflow = app(DummyWorkflow::class)->setSubject($user);

        $workflow->apply('verify_email_address');

        Log::shouldHaveReceived('error')->once()->withArgs(fn (string $message, array $context): bool => $message === 'No valid from state found for transition.'
                && ($context['workflow'] ?? null) === DummyWorkflow::class
                && ($context['transition'] ?? null) === 'verify_email_address'
                && ($context['current_state'] ?? null) === 'register');
    }
}
