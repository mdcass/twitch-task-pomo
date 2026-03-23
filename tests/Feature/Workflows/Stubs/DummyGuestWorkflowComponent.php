<?php

namespace Tests\Feature\Workflows\Stubs;

class DummyGuestWorkflowComponent extends DummyWorkflowComponent
{
    protected function workflowUsesSession(): bool
    {
        return true;
    }
}
