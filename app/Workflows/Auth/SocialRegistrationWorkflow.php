<?php

namespace App\Workflows\Auth;

use App\Workflows\BaseWorkflow;

class SocialRegistrationWorkflow extends BaseWorkflow
{
    protected function places(): array
    {
        return [
            'pending',
            'collect_email',
            'existing_account_handoff',
            'complete',
        ];
    }

    protected function transitions(): array
    {
        return [
            'start_email_collection' => [
                'from' => 'pending',
                'to' => 'collect_email',
            ],
            'show_existing_account_handoff' => [
                'from' => ['pending', 'collect_email'],
                'to' => 'existing_account_handoff',
            ],
            'complete_registration' => [
                'from' => 'collect_email',
                'to' => 'complete',
            ],
        ];
    }
}
