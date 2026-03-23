<?php

namespace Tests\Feature;

use App\Livewire\Offcanvas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use Tests\Feature\Stubs\OffcanvasChildComponent;
use Tests\TestCase;

class OffcanvasComponentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Livewire::component('test.offcanvas-child', OffcanvasChildComponent::class);
    }

    public function test_offcanvas_renders_optional_header_and_footer_slots_only_when_present(): void
    {
        $withChrome = Livewire::test(new class extends Component {
            public bool $show = true;

            public bool $dismissible = true;

            public bool $includeHeader = true;

            public bool $includeFooter = true;

            public ?string $initialFocus = 'focusTarget';

            public string $initialFocusMethod = 'focus';

            public function render(): string
            {
                return <<<'BLADE'
                    <div>
                        <x-offcanvas
                            wire:model.live="show"
                            :dismissible="$dismissible"
                            :initial-focus="$initialFocus"
                            :initial-focus-method="$initialFocusMethod"
                        >
                            @if ($includeHeader)
                                <x-slot name="header">
                                    <h5 class="offcanvas-title">Harness Header</h5>
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
                        </x-offcanvas>
                    </div>
                    BLADE;
            }
        });

        $withChrome->assertSeeHtml('class="offcanvas-header border-bottom"');
        $withChrome->assertSeeHtml('class="offcanvas-footer border-top px-4 py-3"');
        $withChrome->assertSeeTextNormalized(['Harness Header', 'Harness Body', 'Confirm']);

        $withoutChrome = Livewire::test($withChrome->instance(), [
            'includeHeader' => false,
            'includeFooter' => false,
        ]);

        $withoutChrome->assertDontSeeHtml('class="offcanvas-header border-bottom"');
        $withoutChrome->assertDontSeeHtml('class="offcanvas-footer border-top px-4 py-3"');
        $withoutChrome->assertSeeTextNormalized('Harness Body');
    }

    public function test_offcanvas_renders_dismissible_and_initial_focus_contract(): void
    {
        $defaultHtml = Livewire::test(new class extends Component {
            public bool $show = true;

            public bool $dismissible = true;

            public ?string $initialFocus = 'focusTarget';

            public string $initialFocusMethod = 'focus';

            public function render(): string
            {
                return <<<'BLADE'
                    <x-offcanvas
                        wire:model.live="show"
                        :dismissible="$dismissible"
                        :initial-focus="$initialFocus"
                        :initial-focus-method="$initialFocusMethod"
                    >
                        <x-slot name="header">
                            <h5 class="offcanvas-title">Focus Harness</h5>
                        </x-slot>

                        <div>
                            <input type="text" x-ref="focusTarget" value="Harness Value" />
                        </div>
                    </x-offcanvas>
                    BLADE;
            }
        })->html();

        $nonDismissibleHtml = Livewire::test(new class extends Component {
            public bool $show = true;

            public bool $dismissible = true;

            public ?string $initialFocus = 'focusTarget';

            public string $initialFocusMethod = 'focus';

            public function render(): string
            {
                return <<<'BLADE'
                    <x-offcanvas
                        wire:model.live="show"
                        :dismissible="$dismissible"
                        :initial-focus="$initialFocus"
                        :initial-focus-method="$initialFocusMethod"
                    >
                        <x-slot name="header">
                            <h5 class="offcanvas-title">Focus Harness</h5>
                        </x-slot>

                        <div>
                            <input type="text" x-ref="focusTarget" value="Harness Value" />
                        </div>
                    </x-offcanvas>
                    BLADE;
            }
        }, [
            'dismissible' => false,
            'initialFocus' => 'focusTarget',
            'initialFocusMethod' => 'select',
        ])->html();

        $this->assertStringContainsString('overlayOffcanvas', $defaultHtml);
        $this->assertStringContainsString('data-bs-backdrop="true"', $defaultHtml);
        $this->assertStringContainsString('data-bs-keyboard="true"', $defaultHtml);
        $this->assertStringContainsString('focusTarget', $defaultHtml);
        $this->assertStringContainsString('focus', $defaultHtml);

        $this->assertStringContainsString('data-bs-backdrop="static"', $nonDismissibleHtml);
        $this->assertStringContainsString('data-bs-keyboard="false"', $nonDismissibleHtml);
        $this->assertStringContainsString('focusTarget', $nonDismissibleHtml);
        $this->assertStringContainsString('select', $nonDismissibleHtml);
    }

    public function test_offcanvas_passes_non_model_attributes_through_to_the_root_element(): void
    {
        $html = Livewire::test(new class extends Component {
            public bool $show = true;

            public function render(): string
            {
                return <<<'BLADE'
                    <x-offcanvas
                        wire:model.live="show"
                        data-overlay-surface="offcanvas"
                        x-on:overlay-offcanvas-open.window="window.offcanvasOpened = true"
                    >
                        <div>Harness Body</div>
                    </x-offcanvas>
                    BLADE;
            }
        })->html();

        $this->assertStringContainsString('data-overlay-surface="offcanvas"', $html);
        $this->assertStringContainsString('x-on:overlay-offcanvas-open.window="window.offcanvasOpened = true"', $html);
        $this->assertStringNotContainsString('wire:model.live="show"', $html);
    }

    public function test_livewire_offcanvas_host_loads_a_configured_child_component_with_merged_payload(): void
    {
        Livewire::test(Offcanvas::class, [
            'component' => ['test.offcanvas-child', ['mode' => 'edit', 'label' => 'Static Label']],
            'elementId' => 'canvas-edit-offcanvas',
            'title' => 'Edit Canvas',
        ])
            ->call('loaded', [
                'id' => 'canvas-edit-offcanvas',
                'title' => 'Rename Canvas',
                'data' => [
                    'recordId' => 42,
                    'name' => 'Deep Work',
                ],
            ])
            ->assertSet('open', true)
            ->assertSet('readyToLoad', true)
            ->assertSet('offcanvasData', [
                'recordId' => 42,
                'name' => 'Deep Work',
            ])
            ->assertSeeText('Rename Canvas')
            ->assertSeeText('Mode: edit')
            ->assertSeeText('Record: 42')
            ->assertSeeText('Offcanvas: canvas-edit-offcanvas')
            ->assertSeeText('Label: Static Label')
            ->assertSeeText('Payload Name: Deep Work');
    }

    public function test_livewire_offcanvas_host_ignores_events_for_other_ids(): void
    {
        Livewire::test(Offcanvas::class, [
            'component' => ['test.offcanvas-child', ['mode' => 'edit']],
            'elementId' => 'canvas-edit-offcanvas',
            'title' => 'Edit Canvas',
        ])
            ->call('loaded', [
                'id' => 'other-offcanvas',
                'data' => ['recordId' => 42],
            ])
            ->assertSet('open', false)
            ->assertSet('readyToLoad', false)
            ->assertSet('offcanvasData', [])
            ->assertDontSeeText('Record: 42');
    }
}
