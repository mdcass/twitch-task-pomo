<?php

namespace Tests\Feature;

use App\Enums\ActivityEvent;
use App\Enums\TeamType;
use App\Livewire\Auth\SocialRegistrationEmailForm;
use App\Models\Activity;
use App\Models\User;
use App\Models\WorkflowStore;
use App\Notifications\Auth\VerifyEmail;
use App\Workflows\Auth\SocialRegistrationWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class SocialRegistrationEmailFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_social_registration_email_form_redirects_to_register_when_workflow_is_missing(): void
    {
        $this->get(route('register.social-email'))
            ->assertRedirect(route('register', absolute: false));
    }

    public function test_social_registration_email_form_renders_over_guest_layout_when_workflow_exists(): void
    {
        $store = $this->createGuestWorkflowStore([
            'provider' => 'twitch',
            'provider_label' => 'Twitch',
            'provider_user_id' => 'workflow-user-layout',
            'effective_provider_email' => null,
            'display_name' => 'Workflow Layout User',
            'legal_acceptance' => null,
        ]);

        $this->withSession([
            'workflow_store_id.'.SocialRegistrationWorkflow::class => $store->id,
        ])
            ->get(route('register.social-email'))
            ->assertOk()
            ->assertSee('Finish Sign Up')
            ->assertSee('Workflow Layout User');
    }

    public function test_social_registration_email_form_creates_unverified_user_and_provider_auth(): void
    {
        Notification::fake();

        $store = $this->createGuestWorkflowStore([
            'provider' => 'twitch',
            'provider_label' => 'Twitch',
            'provider_user_id' => 'workflow-user-1',
            'effective_provider_email' => null,
            'avatar_url' => 'https://cdn.example.test/avatars/workflow-user-1.png',
            'display_name' => 'Workflow User',
            'scopes' => ['user:read:email'],
            'profile' => ['display_name' => 'Workflow User'],
            'access_token' => Crypt::encryptString('workflow-access-token'),
            'refresh_token' => Crypt::encryptString('workflow-refresh-token'),
            'expires_in' => 3600,
            'legal_acceptance' => [
                'terms_of_service_accepted_at' => '2026-03-22T10:00:00+00:00',
                'privacy_policy_accepted_at' => '2026-03-22T10:00:00+00:00',
            ],
        ]);

        session()->put('workflow_store_id.'.SocialRegistrationWorkflow::class, $store->id);

        Livewire::test(SocialRegistrationEmailForm::class)
            ->set('fields.email', 'workflow@example.test')
            ->call('submit')
            ->assertRedirect(route('verification.notice', absolute: false));

        $user = User::query()->where('email', 'workflow@example.test')->first();

        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
        $user->load('ownedTeams', 'currentTeam');
        $this->assertCount(1, $user->ownedTeams);
        $this->assertSame(TeamType::Streamer, $user->ownedTeams->first()->type);
        $this->assertTrue($user->currentTeam->is($user->ownedTeams->first()));
        $this->assertSame('Workflow\'s Streamer Profile', $user->currentTeam->name);
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);

        $providerAuth = $user->providerAuths()->sole();

        $this->assertSame('workflow-user-1', $providerAuth->provider_user_id);
        $this->assertSame('https://cdn.example.test/avatars/workflow-user-1.png', $providerAuth->avatar_url);
        $this->assertSame('workflow-access-token', $providerAuth->access_token);
        $this->assertSame('workflow-refresh-token', $providerAuth->refresh_token);
        $this->assertSame(['user:read:email'], $providerAuth->scopes);

        $store->refresh();
        $workflow = $store->workflow();

        $this->assertSame('closed', $store->status->value);
        $this->assertInstanceOf(SocialRegistrationWorkflow::class, $workflow);
        $this->assertTrue($workflow->isState('complete'));
        $this->assertSame($user->email, $workflow->getContextValue('complete_registration', 'registered_email'));

        $activity = Activity::query()
            ->where('event', ActivityEvent::AuthSocialRegistrationCompleted->value)
            ->sole();

        $this->assertStringStartsWith('livewire-unit-test-endpoint/', $activity->getExtraProperty('request_path'));
        $this->assertSame('auth.social-registration-email-form', $activity->getExtraProperty('livewire_component'));
        $this->assertSame('submit', $activity->getExtraProperty('livewire_method'));
        $this->assertSame($user->current_team_id, $activity->team_id);
        $this->assertSame(SocialRegistrationWorkflow::class, data_get($activity->properties->toArray(), 'workflow.class'));
        $this->assertSame('complete', data_get($activity->properties->toArray(), 'workflow.state'));
        $this->assertSame(0, Activity::query()->where('subject_type', WorkflowStore::class)->count());
    }

    public function test_social_registration_email_form_transitions_to_existing_account_handoff(): void
    {
        User::factory()->create([
            'email' => 'existing@example.test',
        ]);

        $store = $this->createGuestWorkflowStore([
            'provider' => 'discord',
            'provider_label' => 'Discord',
            'provider_user_id' => 'workflow-user-2',
            'effective_provider_email' => null,
            'display_name' => 'Existing Match',
            'scopes' => ['identify', 'email'],
            'profile' => ['username' => 'Existing Match'],
            'access_token' => Crypt::encryptString('discord-access-token'),
            'refresh_token' => Crypt::encryptString('discord-refresh-token'),
            'expires_in' => 7200,
            'legal_acceptance' => [
                'terms_of_service_accepted_at' => '2026-03-22T10:00:00+00:00',
                'privacy_policy_accepted_at' => '2026-03-22T10:00:00+00:00',
            ],
        ]);

        session()->put('workflow_store_id.'.SocialRegistrationWorkflow::class, $store->id);

        Livewire::test(SocialRegistrationEmailForm::class)
            ->set('fields.email', 'existing@example.test')
            ->call('submit')
            ->assertSeeTextNormalized('already belongs to an existing account here.');

        $this->assertGuest();
        $this->assertDatabaseCount('provider_auths', 0);

        $store->refresh();
        $workflow = $store->workflow();

        $this->assertInstanceOf(SocialRegistrationWorkflow::class, $workflow);
        $this->assertTrue($workflow->isState('existing_account_handoff'));
        $this->assertSame('existing@example.test', $workflow->getContextValue('show_existing_account_handoff', 'attempted_email'));

        $activity = Activity::query()
            ->where('event', ActivityEvent::AuthSocialRegistrationBlockedExistingEmail->value)
            ->sole();

        $this->assertStringStartsWith('livewire-unit-test-endpoint/', $activity->getExtraProperty('request_path'));
        $this->assertSame('auth.social-registration-email-form', $activity->getExtraProperty('livewire_component'));
        $this->assertSame('submit', $activity->getExtraProperty('livewire_method'));
        $this->assertSame('entered_existing_email', $activity->getExtraProperty('reason'));
        $this->assertSame(SocialRegistrationWorkflow::class, data_get($activity->properties->toArray(), 'workflow.class'));
        $this->assertSame('existing_account_handoff', data_get($activity->properties->toArray(), 'workflow.state'));
        $this->assertSame(0, Activity::query()->where('subject_type', WorkflowStore::class)->count());
    }

    public function test_social_registration_email_form_maps_invalid_email_errors_inline(): void
    {
        $store = $this->createGuestWorkflowStore([
            'provider' => 'twitch',
            'provider_label' => 'Twitch',
            'provider_user_id' => 'workflow-user-3',
            'effective_provider_email' => null,
            'display_name' => 'Workflow User',
            'scopes' => ['user:read:email'],
            'profile' => ['display_name' => 'Workflow User'],
            'access_token' => Crypt::encryptString('workflow-access-token'),
            'refresh_token' => Crypt::encryptString('workflow-refresh-token'),
            'expires_in' => 3600,
            'legal_acceptance' => null,
        ]);

        session()->put('workflow_store_id.'.SocialRegistrationWorkflow::class, $store->id);

        Livewire::test(SocialRegistrationEmailForm::class)
            ->set('fields.email', 'not-an-email')
            ->call('submit')
            ->assertSeeText('The email address field must be a valid email address.')
            ->assertHasErrors(['fields.email']);

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function createGuestWorkflowStore(array $context): WorkflowStore
    {
        return WorkflowStore::factory()->create([
            'team_id' => null,
            'created_by_user_id' => null,
            'workflow_class' => SocialRegistrationWorkflow::class,
            'records' => [
                [
                    'from' => 'pending',
                    'to' => 'collect_email',
                    'context' => $context,
                    'failed' => false,
                    'timestamp' => now()->toDateTimeString(),
                ],
            ],
        ]);
    }
}
