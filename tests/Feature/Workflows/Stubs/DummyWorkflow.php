<?php

namespace Tests\Feature\Workflows\Stubs;

use App\Workflows\BaseWorkflow;
use App\Workflows\GuardResult;

class DummyWorkflow extends BaseWorkflow
{
    public array $errorPlaces = [];

    protected function places(): array
    {
        $places = [
            'register',
            'password',
            'complete',
            'api_access',
        ];

        return $this->errorPlaces === []
            ? $places
            : array_merge(array_fill_keys($places, false), array_fill_keys($this->errorPlaces, true));
    }

    protected function transitions(): array
    {
        return [
            'set_password' => [
                'from' => 'register',
                'to' => 'password',
                'guard' => 'guardSetPassword',
            ],
            'verify_email_address' => [
                'from' => 'password',
                'to' => 'complete',
            ],
            'activate_api_access' => [
                'from' => 'password',
                'to' => 'api_access',
                'guard' => 'guardActivateApiAccess',
            ],
            'fallback_only_transition' => [
                'from' => 'register',
                'to' => 'complete',
            ],
        ];
    }

    public function guardActivateApiAccess(): GuardResult
    {
        return GuardResult::allowed();
    }

    public function guardSetPassword(): GuardResult
    {
        return is_string($this->getSubject()?->email) && $this->getSubject()?->email !== ''
            ? GuardResult::allowed()
            : GuardResult::blocked('Email is required to set password.');
    }

    public function guardVerifyEmailAddress(): GuardResult
    {
        return $this->getSubject()?->email_verified_at !== null
            ? GuardResult::allowed()
            : GuardResult::blocked('Email must be verified before completing workflow.');
    }

    public function guard(string $transition): GuardResult
    {
        if ($transition === 'fallback_only_transition') {
            return GuardResult::blocked('Blocked by fallback guard');
        }

        return GuardResult::allowed();
    }
}
