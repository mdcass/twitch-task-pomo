<?php

namespace Tests\Feature\Workflows\Stubs;

use App\Livewire\Workflows\Concerns\LivewireWorkflow;
use App\Workflows\GuardResult;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class DummyWorkflowComponent extends Component
{
    use LivewireWorkflow;

    public bool $allowSetPassword = false;

    public bool $allowFallback = false;

    public function mount(): void
    {
        $this->mountWorkflowComponent();

        if (Auth::user() !== null) {
            $this->workflow->setSubject(Auth::user());
        }
    }

    public function places(): array
    {
        return [
            'register',
            'password',
            'complete',
        ];
    }

    public function transitions(): array
    {
        return [
            'set_password' => [
                'from' => 'register',
                'to' => 'password',
                'guard' => 'guardSetPassword',
            ],
            'fallback_only_transition' => [
                'from' => 'register',
                'to' => 'complete',
            ],
        ];
    }

    public function guardSetPassword(): GuardResult
    {
        return $this->allowSetPassword
            ? GuardResult::allowed()
            : GuardResult::blocked('Set password blocked on component');
    }

    public function guard(string $transition): GuardResult
    {
        if ($transition === 'fallback_only_transition' && ! $this->allowFallback) {
            return GuardResult::blocked('Fallback transition blocked on component');
        }

        return GuardResult::allowed();
    }

    public function render()
    {
        return view('livewire.testing.workflow-dummy');
    }
}
