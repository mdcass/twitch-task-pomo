<?php

namespace App\Contracts;

use App\Enums\Models\WorkflowStatus;
use App\Models\User;
use App\Models\WorkflowStore;
use App\Workflows\GuardResult;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Collection;

interface Workflow
{
    public static function fromSession(): ?static;

    public static function fromStore(WorkflowStore $store): static;

    public static function fromSubject(User $user, EloquentModel $subject): ?static;

    public function apply(string $transition, array $context = []): void;

    public function can(string $transition): bool;

    public function close(): self;

    public function getContextValue(string $transition, string $key, mixed $default = null): mixed;

    public function getLastFailedTransition(string $transitionName): ?array;

    public function getLastTransition(string $transitionName): array;

    public function getRecords(): array;

    public function getState(): ?string;

    public function getStatus(): WorkflowStatus;

    public function getStore(): ?WorkflowStore;

    public function getSubject(): ?EloquentModel;

    public function guardFor(string $transition, mixed $guardResult = null): GuardResult;

    public function isState(Collection|array|string $state): bool;

    public function refreshStore(): self;

    public function runGuardMethodsCallback(string $transition): callable;

    public function saveStore(bool $useSession = false): self;

    public function setStatus(WorkflowStatus $status): self;

    public function setSubject(EloquentModel $subject): self;

    public function toArray(): array;
}
