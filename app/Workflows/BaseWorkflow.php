<?php

namespace App\Workflows;

use App\Contracts\Workflow;
use App\Enums\Models\WorkflowStatus;
use App\Exceptions\WorkflowTransitionException;
use App\Models\User;
use App\Models\WorkflowStore;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

abstract class BaseWorkflow implements Workflow
{
    protected ?WorkflowStore $store = null;

    abstract protected function places(): array;

    abstract protected function transitions(): array;

    public static function fromSession(): ?static
    {
        $workflowId = Session::get('workflow_store_id.'.static::class);

        if (! is_int($workflowId) && ! ctype_digit((string) $workflowId)) {
            return null;
        }

        $store = WorkflowStore::query()->find((int) $workflowId);

        if (! $store instanceof WorkflowStore) {
            return null;
        }

        $user = Auth::user();
        if (
            $user instanceof User
            && is_int($user->current_team_id)
            && is_int($store->team_id)
            && $store->team_id !== $user->current_team_id
        ) {
            return null;
        }

        return static::fromStore($store);
    }

    public static function fromStore(WorkflowStore $store): static
    {
        $workflow = new static(
            class: $store->workflow_class,
            subject: $store->subject,
            status: $store->status,
            records: $store->records ?? [],
        );

        return $workflow->setStore($store);
    }

    public static function fromSubject(User $user, EloquentModel $subject): ?static
    {
        $team = $user->currentTeam;

        if ($team === null) {
            return null;
        }

        $store = $team->workflowStores()
            ->where('subject_type', $subject::class)
            ->where('subject_id', $subject->getKey())
            ->where('workflow_class', static::class)
            ->first();

        return $store ? static::fromStore($store) : null;
    }

    public function __construct(
        protected ?string $class = null,
        protected ?EloquentModel $subject = null,
        protected WorkflowStatus $status = WorkflowStatus::OPEN,
        protected array $records = [],
    ) {
        $places = array_keys($this->getPlaces());

        foreach ($this->transitions() as $transitionName => $definition) {
            $fromStates = is_array($definition['from']) ? $definition['from'] : [$definition['from']];
            $toStates = is_array($definition['to']) ? $definition['to'] : [$definition['to']];

            foreach ($fromStates as $fromState) {
                if (! in_array($fromState, $places, true)) {
                    throw new \InvalidArgumentException(sprintf(
                        "Transition '%s': 'from' state '%s' does not exist in places().",
                        $transitionName,
                        $fromState,
                    ));
                }
            }

            foreach ($toStates as $toState) {
                if (! in_array($toState, $places, true)) {
                    throw new \InvalidArgumentException(sprintf(
                        "Transition '%s': 'to' state '%s' does not exist in places().",
                        $transitionName,
                        $toState,
                    ));
                }
            }
        }
    }

    public function apply(string $transition, array $context = [], ?GuardResult $guardResult = null): void
    {
        $guardResult ??= $this->guardFor($transition);

        $definition = $this->transitionDefinition($transition);

        $fromState = $this->getValidFromState($definition);

        if ($fromState === null) {
            Log::error('No valid from state found for transition.', [
                'user_id' => Auth::id(),
                'workflow' => static::class,
                'transition' => $transition,
                'current_state' => $this->getState(),
                'records' => $this->getRecords(),
            ]);

            return;
        }

        if (! $guardResult->passes()) {
            $context = array_merge([
                'status' => 'failed',
                'error_message' => $guardResult->message,
            ], $context);

            $this->recordTransition(
                from: $fromState,
                to: $definition['to'],
                context: $context,
                failed: true,
            );

            throw new WorkflowTransitionException($guardResult, $transition);
        }

        $this->recordTransition($fromState, $definition['to'], $context);
    }

    final public function can(string $transition): bool
    {
        return $this->guardFor($transition)->passes();
    }

    final public function close(): self
    {
        $this->status = WorkflowStatus::CLOSED;

        return $this;
    }

    final public function getContextValue(string $transition, string $key, mixed $default = null): mixed
    {
        $context = $this->getTransitionContext($transition);

        return data_get($context, $key, $default);
    }

    /**
     * @return array<string, mixed>
     */
    final public function getInitialContext(): array
    {
        foreach ($this->getRecords() as $record) {
            if (($record['failed'] ?? false) === true) {
                continue;
            }

            $context = $record['context'] ?? null;

            if (is_array($context)) {
                return $context;
            }
        }

        return [];
    }

    final public function getInitialContextValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->getInitialContext(), $key, $default);
    }

    final public function getFailedTransitions(): array
    {
        return collect($this->getRecords())
            ->where('failed', true)
            ->all();
    }

    final public function getLastFailedTransition(string $transitionName): ?array
    {
        $definition = $this->transitionDefinition($transitionName);

        return collect($this->getFailedTransitions())
            ->reverse()
            ->first(fn (array $transition) => $transition['to'] === $definition['to']);
    }

    final public function getLastTransition(string $transitionName): array
    {
        $definition = $this->transitionDefinition($transitionName);

        $transition = collect($this->getRecords())
            ->reverse()
            ->first(fn (array $record) => $record['to'] === $definition['to']);

        return $transition ?? throw new \InvalidArgumentException("Transition '{$transitionName}' has not occurred.");
    }

    final public function getRecords(): array
    {
        return $this->records;
    }

    final public function getState(): ?string
    {
        $record = collect($this->getRecords())
            ->reverse()
            ->first(fn (array $record) => empty($record['failed']));

        if (is_array($record) && is_string($record['to'] ?? null)) {
            return $record['to'];
        }

        return array_key_first($this->getPlaces());
    }

    final public function getStatus(): WorkflowStatus
    {
        return $this->isErrorState()
            ? WorkflowStatus::ERROR
            : $this->status;
    }

    final public function getStore(): ?WorkflowStore
    {
        return $this->store;
    }

    final public function getSubject(): ?EloquentModel
    {
        return $this->subject;
    }

    final public function getTransitionContext(string $transitionName): ?array
    {
        $transition = $this->getLastTransition($transitionName);

        return $transition['context'] ?? null;
    }

    final public function guardFor(string $transition, mixed $guardResult = null): GuardResult
    {
        $definition = $this->transitionDefinition($transition);

        $fromStates = is_array($definition['from']) ? $definition['from'] : [$definition['from']];
        $inRequiredState = false;

        foreach ($fromStates as $fromState) {
            if ($this->isState($fromState)) {
                $inRequiredState = true;
                break;
            }
        }

        if (! $inRequiredState) {
            return GuardResult::blocked("Current states do not include any required 'from' states");
        }

        return $this->getGuardResult(
            transition: $transition,
            guard: $definition['guard'] ?? null,
            guardResult: $guardResult,
        );
    }

    final public function hasTransitionFailed(string $transitionName): bool
    {
        $definition = $this->transitionDefinition($transitionName);

        return collect($this->getFailedTransitions())
            ->contains(fn (array $transition) => $transition['to'] === $definition['to']);
    }

    final public function hasTransitionOccurred(string $transitionName): bool
    {
        $definition = $this->transitionDefinition($transitionName);

        return collect($this->getRecords())
            ->contains(fn (array $transition) => $transition['to'] === $definition['to']);
    }

    final public function isState(Collection|array|string $state): bool
    {
        if ($state instanceof Collection || is_array($state)) {
            $states = $state instanceof Collection ? $state->all() : $state;

            return in_array($this->getState(), $states, true);
        }

        return $this->getState() === $state;
    }

    final public function refreshStore(): self
    {
        if (! $this->store instanceof WorkflowStore) {
            throw new \RuntimeException('Workflow store has not been set.');
        }

        return self::fromStore($this->store->refresh());
    }

    final public function runGuardMethodsCallback(string $transition): callable
    {
        $definition = $this->transitionDefinition($transition);

        $methods = [
            $definition['guard'] ?? self::generateGuardMethodName($transition),
            'guard',
        ];

        return function () use ($transition, $methods) {
            foreach ($methods as $method) {
                if (! method_exists($this, $method)) {
                    continue;
                }

                $result = $method === 'guard'
                    ? $this->{$method}($transition)
                    : $this->{$method}();

                if (! $result instanceof GuardResult) {
                    throw new \InvalidArgumentException("Guard method '{$method}' must return a GuardResult object.");
                }

                return $result;
            }

            return null;
        };
    }

    final public function saveStore(bool $useSession = false): self
    {
        $user = Auth::user();
        $subject = $this->getSubject();
        $isAuthenticated = $user instanceof User;

        if (! $isAuthenticated && $subject !== null) {
            throw new \RuntimeException('Authenticated user required to persist a subject-backed workflow.');
        }

        $team = $isAuthenticated ? $user->currentTeam : null;

        if ($isAuthenticated && $team === null) {
            throw new \RuntimeException('Current team required to persist workflow.');
        }

        $payload = [
            ...$this->toArray(),
            'team_id' => $team?->id,
            'created_by_user_id' => $user?->id,
        ];

        if ($this->store instanceof WorkflowStore) {
            if (
                $team !== null
                && is_int($this->store->team_id)
                && $this->store->team_id !== $team->id
            ) {
                throw new \RuntimeException('Cannot persist workflow across team boundaries.');
            }

            if ($team === null && is_int($this->store->team_id)) {
                throw new \RuntimeException('Authenticated user required to update a team-scoped workflow.');
            }

            $this->store->update($payload);
        } else {
            if ($team === null) {
                $this->store = WorkflowStore::query()->create($payload);
            } else {
                [$attributes, $values] = collect($payload)->partition(
                    fn (mixed $value, string $key) => in_array($key, ['workflow_class', 'subject_type', 'subject_id'], true),
                );

                $this->store = $team->workflowStores()->updateOrCreate($attributes->all(), $values->all());
            }
        }

        if ($useSession) {
            Session::put('workflow_store_id.'.$this->store->workflow_class, $this->store->id);
        }

        return $this;
    }

    final public function setStatus(WorkflowStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    final public function setSubject(EloquentModel $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    final public function setStore(WorkflowStore $store): self
    {
        $this->store = $store;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'subject_type' => $this->subject ? $this->subject::class : null,
            'subject_id' => $this->subject?->getKey(),
            'workflow_class' => $this->class ?? static::class,
            'status' => $this->status->value,
            'records' => $this->getRecords(),
        ];
    }

    final public function getErrorPlaces(): array
    {
        return array_keys(array_filter($this->getPlaces(), fn (bool $value) => $value));
    }

    protected function getPlaces(): array
    {
        $places = $this->places();

        return array_is_list($places)
            ? array_fill_keys($places, false)
            : $places;
    }

    protected function getValidFromState(array $definition): ?string
    {
        $fromStates = is_array($definition['from']) ? $definition['from'] : [$definition['from']];

        foreach ($fromStates as $fromState) {
            if ($this->isState($fromState)) {
                return $fromState;
            }
        }

        return null;
    }

    protected function isErrorState(): bool
    {
        $state = $this->getState();

        if (! is_string($state)) {
            return false;
        }

        return ! empty($this->getPlaces()[$state]);
    }

    protected function recordTransition(string $from, array|string $to, array $context = [], bool $failed = false): void
    {
        $this->records[] = [
            'from' => $from,
            'to' => $to,
            'context' => $context,
            'failed' => $failed,
            'timestamp' => Date::now()->toDateTimeString(),
        ];
    }

    private function transitionDefinition(string $transition): array
    {
        $definition = $this->transitions()[$transition] ?? null;

        if (! is_array($definition)) {
            throw new \InvalidArgumentException("Transition '{$transition}' does not exist.");
        }

        return $definition;
    }

    private function getGuardResult(string $transition, ?string $guard, mixed $guardResult): GuardResult
    {
        if ($guardResult !== null) {
            if (! $guardResult instanceof GuardResult) {
                throw new \InvalidArgumentException("Guard method '{$guard}' must return a GuardResult object.");
            }

            return $guardResult;
        }

        $guardMethod = $guard ?? self::generateGuardMethodName($transition);

        if (method_exists($this, $guardMethod)) {
            return $this->callGuardMethod($guardMethod);
        }

        if ($guard !== null) {
            if (class_exists($guard) && is_a($guard, GuardContract::class, true)) {
                $guardInstance = new $guard($this->subject);

                return $guardInstance->passes()
                    ? GuardResult::allowed()
                    : GuardResult::blocked($guardInstance->message() ?? "Guard class '{$guard}' blocked the transition.");
            }

            throw new \InvalidArgumentException(sprintf(
                "Guard '%s' is neither a method on %s nor a class implementing GuardContract.",
                $guard,
                get_class($this),
            ));
        }

        if (method_exists($this, 'guard')) {
            return $this->callGuardMethod('guard', $transition);
        }

        return GuardResult::allowed();
    }

    private function callGuardMethod(string $method, ?string $transition = null): GuardResult
    {
        $result = $transition === null ? $this->{$method}() : $this->{$method}($transition);

        if (! $result instanceof GuardResult) {
            throw new \InvalidArgumentException("Guard method '{$method}' must return a GuardResult object.");
        }

        return $result;
    }

    private static function generateGuardMethodName(string $transition): string
    {
        return 'guard'.str($transition)->camel()->ucfirst();
    }
}
