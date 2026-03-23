<?php

namespace App\Livewire\Auth;

use App\Actions\Auth\CompleteSocialRegistration;
use App\Actions\Auth\CompleteRegistration;
use App\Workflows\Auth\SocialRegistrationWorkflow;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

class SocialRegistrationEmailForm extends Component
{
    public array $fields = [
        'email' => '',
    ];

    private ?SocialRegistrationWorkflow $resolvedWorkflow = null;

    public function mount(): void
    {
        if ($this->workflow() === null) {
            $this->redirectRoute('register', navigate: true);

            return;
        }

        if ($this->isCollectingEmail()) {
            $this->fields['email'] = (string) ($this->workflow()?->getInitialContextValue('entered_email') ?? '');
        }
    }

    public function submit(
        CompleteSocialRegistration $registration,
        CompleteRegistration $completeRegistration,
    ) {
        $this->resetErrorBag();

        $workflow = $this->workflow();

        if (! $workflow instanceof SocialRegistrationWorkflow) {
            return $this->redirectRoute('register', navigate: true);
        }

        if (! $workflow->isState('collect_email')) {
            return null;
        }

        try {
            $email = $registration->validateEmail((string) ($this->fields['email'] ?? ''));
            $this->fields['email'] = $email;

            if ($registration->emailBelongsToExistingUser($email)) {
                $workflow->apply('show_existing_account_handoff', [
                    'attempted_email' => $email,
                ]);
                $workflow->saveStore(useSession: true);

                return null;
            }

            $user = $registration->complete([
                'name' => $workflow->getInitialContextValue('display_name', $this->providerLabel().' User'),
                'email' => $email,
                'provider' => $workflow->getInitialContextValue('provider'),
                'provider_user_id' => $workflow->getInitialContextValue('provider_user_id'),
                'provider_email' => $workflow->getInitialContextValue('effective_provider_email'),
                'avatar_url' => $workflow->getInitialContextValue('avatar_url'),
                'access_token' => $this->decryptOptionalString($workflow->getInitialContextValue('access_token')),
                'refresh_token' => $this->decryptOptionalString($workflow->getInitialContextValue('refresh_token')),
                'expires_in' => $workflow->getInitialContextValue('expires_in'),
                'scopes' => $workflow->getInitialContextValue('scopes', []),
                'profile' => $workflow->getInitialContextValue('profile', []),
                'legal_acceptance' => $workflow->getInitialContextValue('legal_acceptance'),
            ]);
        } catch (ValidationException $exception) {
            $emailError = $exception->validator->errors()->first('email');

            if ($emailError !== '') {
                $this->addError('fields.email', $emailError);
            }

            return null;
        }

        $workflow->apply('complete_registration', [
            'registered_user_id' => $user->id,
            'registered_email' => $user->email,
        ]);
        $workflow->close()->saveStore(useSession: true);

        session()->forget('workflow_store_id.'.SocialRegistrationWorkflow::class);

        $completeRegistration->handle(request(), $user);

        return redirect()->route('verification.notice');
    }

    #[Computed]
    public function isCollectingEmail(): bool
    {
        return $this->workflow()?->isState('collect_email') ?? false;
    }

    #[Computed]
    public function isExistingAccountHandoff(): bool
    {
        return $this->workflow()?->isState('existing_account_handoff') ?? false;
    }

    #[Computed]
    public function providerLabel(): string
    {
        return (string) ($this->workflow()?->getInitialContextValue('provider_label') ?? 'Provider');
    }

    #[Computed]
    public function displayName(): string
    {
        return (string) ($this->workflow()?->getInitialContextValue('display_name') ?? $this->providerLabel().' User');
    }

    #[Computed]
    public function attemptedEmail(): ?string
    {
        $workflow = $this->workflow();

        if (! $workflow instanceof SocialRegistrationWorkflow || ! $workflow->hasTransitionOccurred('show_existing_account_handoff')) {
            return null;
        }

        return $workflow->getContextValue('show_existing_account_handoff', 'attempted_email');
    }

    #[Computed]
    public function debugOverride(): ?string
    {
        $value = $this->workflow()?->getInitialContextValue('debug_override');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function render()
    {
        return view('livewire.auth.social-registration-email-form')
            ->layout('components.layouts.guest', [
                'variant' => 'simple',
            ]);
    }

    private function workflow(): ?SocialRegistrationWorkflow
    {
        return $this->resolvedWorkflow ??= SocialRegistrationWorkflow::fromSession();
    }

    private function decryptOptionalString(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return Crypt::decryptString($value);
    }
}
