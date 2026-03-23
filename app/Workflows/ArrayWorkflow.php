<?php

namespace App\Workflows;

use App\Enums\Models\WorkflowStatus;
use App\Models\WorkflowStore;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Facades\Crypt;
use Livewire\Wireable;

class ArrayWorkflow extends BaseWorkflow implements Wireable
{
    public static function fromStore(WorkflowStore $store, array $places = [], array $transitions = []): static
    {
        $workflow = new static(
            class: $store->workflow_class,
            subject: $store->subject,
            status: $store->status,
            records: $store->records ?? [],
            places: $places,
            transitions: $transitions,
        );

        return $workflow->setStore($store);
    }

    public function __construct(
        ?string $class = null,
        ?EloquentModel $subject = null,
        WorkflowStatus $status = WorkflowStatus::OPEN,
        array $records = [],
        protected array $places = [],
        protected array $transitions = [],
    ) {
        parent::__construct($class, $subject, $status, $records);
    }

    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'store_id' => $this->getStore()?->getKey(),
            'places' => $this->places,
            'transitions' => $this->transitions,
        ];
    }

    public function toLivewire(): array
    {
        return [
            'payload' => Crypt::encryptString(json_encode($this->toArray(), JSON_THROW_ON_ERROR)),
        ];
    }

    protected function places(): array
    {
        return $this->places;
    }

    protected function transitions(): array
    {
        return $this->transitions;
    }

    public static function fromLivewire(mixed $value): self
    {
        if (! is_array($value) || ! is_string($value['payload'] ?? null)) {
            throw new \InvalidArgumentException('Invalid data for ArrayWorkflow deserialization.');
        }

        $data = json_decode(Crypt::decryptString($value['payload']), true, flags: JSON_THROW_ON_ERROR);

        $subject = ($data['subject_type'] ?? null) && ($data['subject_id'] ?? null)
            ? $data['subject_type']::findOrFail($data['subject_id'])
            : null;

        $workflow = new static(
            class: $data['workflow_class'] ?? static::class,
            subject: $subject,
            status: WorkflowStatus::from($data['status'] ?? WorkflowStatus::OPEN->value),
            records: $data['records'] ?? [],
            places: $data['places'] ?? [],
            transitions: $data['transitions'] ?? [],
        );

        $storeId = $data['store_id'] ?? null;

        if (is_int($storeId) || ctype_digit((string) $storeId)) {
            $store = WorkflowStore::query()->find((int) $storeId);

            if ($store instanceof WorkflowStore) {
                $workflow->setStore($store);
            }
        }

        return $workflow;
    }
}
