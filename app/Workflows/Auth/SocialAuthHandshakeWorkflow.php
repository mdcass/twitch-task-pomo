<?php

namespace App\Workflows\Auth;

use App\Workflows\BaseWorkflow;

class SocialAuthHandshakeWorkflow extends BaseWorkflow
{
    protected function places(): array
    {
        return [
            'pending',
            'redirected',
            'callback_failed',
            'login_complete',
            'registration_complete',
            'registration_handoff',
        ];
    }

    protected function transitions(): array
    {
        return [
            'start_redirect' => [
                'from' => 'pending',
                'to' => 'redirected',
            ],
            'fail_callback' => [
                'from' => 'redirected',
                'to' => 'callback_failed',
            ],
            'complete_login' => [
                'from' => 'redirected',
                'to' => 'login_complete',
            ],
            'complete_registration' => [
                'from' => 'redirected',
                'to' => 'registration_complete',
            ],
            'handoff_registration' => [
                'from' => 'redirected',
                'to' => 'registration_handoff',
            ],
        ];
    }
}
