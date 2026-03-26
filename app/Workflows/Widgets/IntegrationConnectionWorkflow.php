<?php

namespace App\Workflows\Widgets;

use App\Workflows\BaseWorkflow;

class IntegrationConnectionWorkflow extends BaseWorkflow
{
    protected function places(): array
    {
        return [
            'pending',
            'redirected',
            'completed',
            'callback_failed',
        ];
    }

    protected function transitions(): array
    {
        return [
            'start_redirect' => [
                'from' => 'pending',
                'to' => 'redirected',
            ],
            'complete_connection' => [
                'from' => 'redirected',
                'to' => 'completed',
            ],
            'fail_callback' => [
                'from' => 'redirected',
                'to' => 'callback_failed',
            ],
        ];
    }
}
