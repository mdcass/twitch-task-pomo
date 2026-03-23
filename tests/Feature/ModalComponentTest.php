<?php

namespace Tests\Feature;

use App\Livewire\Modal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use Tests\Feature\Stubs\ModalChildComponent;
use Tests\TestCase;

class ModalComponentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Livewire::component('test.modal-child', ModalChildComponent::class);
    }

    public function test_modal_renders_optional_header_and_footer_slots_only_when_present(): void
    {
        $withChrome = Livewire::test(new class extends Component {
            public bool $show = true;

            public bool $dismissible = true;

            public bool $includeHeader = true;

            public bool $includeFooter = true;

            public ?string $initialFocus = 'focusTarget';

            public string $initialFocusMethod = 'focus';

            public function mount(
                bool $dismissible = true,
                bool $includeHeader = true,
                bool $includeFooter = true,
                ?string $initialFocus = 'focusTarget',
                string $initialFocusMethod = 'focus',
            ): void {
                $this->dismissible = $dismissible;
                $this->includeHeader = $includeHeader;
                $this->includeFooter = $includeFooter;
                $this->initialFocus = $initialFocus;
                $this->initialFocusMethod = $initialFocusMethod;
            }

            public function render(): string
            {
                return <<<'BLADE'
                    <div>
                        <button type="button">Open Modal</button>

                        <x-modal
                            wire:model.live="show"
                            :dismissible="$dismissible"
                            :initial-focus="$initialFocus"
                            :initial-focus-method="$initialFocusMethod"
                        >
                            @if ($includeHeader)
                                <x-slot name="header">
                                    <h5 class="modal-title">Harness Header</h5>
                                </x-slot>
                            @endif

                            <div>
                                <p class="mb-0 text-body-secondary">Harness Body</p>
                                <input type="text" x-ref="focusTarget" value="Harness Value" />
                            </div>

                            @if ($includeFooter)
                                <x-slot name="footer">
                                    <button type="button" class="btn btn-primary">Confirm</button>
                                </x-slot>
                            @endif
                        </x-modal>
                    </div>
                    BLADE;
            }
        });

        $withChrome->assertSeeHtml('class="modal-header"');
        $withChrome->assertSeeHtml('class="modal-footer"');
        $withChrome->assertSeeTextNormalized(['Harness Header', 'Harness Body', 'Confirm']);

        $withoutChrome = Livewire::test($withChrome->instance(), [
            'includeHeader' => false,
            'includeFooter' => false,
        ]);

        $withoutChrome->assertDontSeeHtml('class="modal-header"');
        $withoutChrome->assertDontSeeHtml('class="modal-footer"');
        $withoutChrome->assertSeeTextNormalized('Harness Body');
    }

    public function test_modal_renders_dismissible_and_initial_focus_contract(): void
    {
        $defaultHtml = Livewire::test(new class extends Component {
            public bool $show = true;

            public bool $dismissible = true;

            public ?string $initialFocus = 'focusTarget';

            public string $initialFocusMethod = 'focus';

            public function mount(
                bool $dismissible = true,
                ?string $initialFocus = 'focusTarget',
                string $initialFocusMethod = 'focus',
            ): void {
                $this->dismissible = $dismissible;
                $this->initialFocus = $initialFocus;
                $this->initialFocusMethod = $initialFocusMethod;
            }

            public function render(): string
            {
                return <<<'BLADE'
                    <x-modal
                        wire:model.live="show"
                        :dismissible="$dismissible"
                        :initial-focus="$initialFocus"
                        :initial-focus-method="$initialFocusMethod"
                    >
                        <x-slot name="header">
                            <h5 class="modal-title">Focus Harness</h5>
                        </x-slot>

                        <div>
                            <input type="text" x-ref="focusTarget" value="Harness Value" />
                        </div>
                    </x-modal>
                    BLADE;
            }
        })->html();

        $nonDismissibleHtml = Livewire::test(new class extends Component {
            public bool $show = true;

            public bool $dismissible = true;

            public ?string $initialFocus = 'focusTarget';

            public string $initialFocusMethod = 'focus';

            public function mount(
                bool $dismissible = true,
                ?string $initialFocus = 'focusTarget',
                string $initialFocusMethod = 'focus',
            ): void {
                $this->dismissible = $dismissible;
                $this->initialFocus = $initialFocus;
                $this->initialFocusMethod = $initialFocusMethod;
            }

            public function render(): string
            {
                return <<<'BLADE'
                    <x-modal
                        wire:model.live="show"
                        :dismissible="$dismissible"
                        :initial-focus="$initialFocus"
                        :initial-focus-method="$initialFocusMethod"
                    >
                        <x-slot name="header">
                            <h5 class="modal-title">Focus Harness</h5>
                        </x-slot>

                        <div>
                            <input type="text" x-ref="focusTarget" value="Harness Value" />
                        </div>
                    </x-modal>
                    BLADE;
            }
        }, [
            'dismissible' => false,
            'initialFocus' => 'focusTarget',
            'initialFocusMethod' => 'select',
        ])->html();

        $this->assertStringContainsString('dismissible: true', $defaultHtml);
        $this->assertStringContainsString('initialFocus', $defaultHtml);
        $this->assertStringContainsString('focusTarget', $defaultHtml);
        $this->assertStringContainsString('initialFocusMethod', $defaultHtml);
        $this->assertStringContainsString('focus', $defaultHtml);

        $this->assertStringContainsString('dismissible: false', $nonDismissibleHtml);
        $this->assertStringContainsString('initialFocus', $nonDismissibleHtml);
        $this->assertStringContainsString('focusTarget', $nonDismissibleHtml);
        $this->assertStringContainsString('initialFocusMethod', $nonDismissibleHtml);
        $this->assertStringContainsString('select', $nonDismissibleHtml);
    }

    public function test_profile_page_renders_the_destructive_delete_account_modal_with_the_base_component(): void
    {
        $user = \App\Models\User::factory()->withStreamerTeam()->create();

        $this->actingAs($user)
            ->get(route('profile.show', absolute: false))
            ->assertOk()
            ->assertSee('Delete Account', false)
            ->assertSee('bg-danger-subtle text-danger', false)
            ->assertSee('aria-labelledby=', false)
            ->assertSee('aria-describedby=', false)
            ->assertDontSee('x-dialog-modal', false)
            ->assertDontSee('x-confirmation-modal', false);
    }

    public function test_livewire_modal_host_loads_a_configured_child_component_with_merged_payload(): void
    {
        Livewire::test(Modal::class, [
            'component' => ['test.modal-child', ['mode' => 'edit', 'label' => 'Static Label']],
            'elementId' => 'task-edit-modal',
            'title' => 'Edit Task',
        ])
            ->call('loaded', [
                'id' => 'task-edit-modal',
                'title' => 'Rename Task',
                'data' => [
                    'recordId' => 42,
                    'name' => 'Deep Work',
                ],
            ])
            ->assertSet('open', true)
            ->assertSet('readyToLoad', true)
            ->assertSet('modalData', [
                'recordId' => 42,
                'name' => 'Deep Work',
            ])
            ->assertSeeText('Rename Task')
            ->assertSeeText('Mode: edit')
            ->assertSeeText('Record: 42')
            ->assertSeeText('Modal: task-edit-modal')
            ->assertSeeText('Label: Static Label')
            ->assertSeeText('Payload Name: Deep Work');
    }

    public function test_livewire_modal_host_ignores_events_for_other_modal_ids(): void
    {
        Livewire::test(Modal::class, [
            'component' => ['test.modal-child', ['mode' => 'edit']],
            'elementId' => 'task-edit-modal',
            'title' => 'Edit Task',
        ])
            ->call('loaded', [
                'id' => 'other-modal',
                'data' => ['recordId' => 42],
            ])
            ->assertSet('open', false)
            ->assertSet('readyToLoad', false)
            ->assertSet('modalData', [])
            ->assertDontSeeText('Record: 42');
    }

    public function test_livewire_modal_host_reopens_a_configured_child_without_a_new_payload(): void
    {
        $modal = Livewire::test(Modal::class, [
            'component' => ['test.modal-child', ['mode' => 'edit']],
            'elementId' => 'task-edit-modal',
            'title' => 'Edit Task',
        ]);

        $modal->call('openFromEvent', ['id' => 'task-edit-modal'])
            ->assertSet('open', true)
            ->assertSet('readyToLoad', true)
            ->assertSeeText('Edit Task')
            ->assertSeeText('Mode: edit')
            ->assertSeeText('Record: none');

        $modal->set('open', false)
            ->assertSet('readyToLoad', false)
            ->assertSet('modalData', [])
            ->assertSet('title', 'Edit Task');

        $modal->call('openFromEvent', ['id' => 'task-edit-modal'])
            ->assertSet('open', true)
            ->assertSet('readyToLoad', true)
            ->assertSeeText('Mode: edit')
            ->assertSeeText('Record: none');
    }
}
