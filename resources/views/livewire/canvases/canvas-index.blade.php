<div class="d-flex flex-column gap-4">
    <section class="py-3">
        <h2 class="h3 mb-3">{{ __('Recent canvases') }}</h2>
        <div class="row g-3">
            @foreach ($this->recent_canvases as $canvas)
                <div class="col-md-6 col-xl-3">
                    <div class="card h-100 border border-translucent shadow-sm">
                        <div class="card-body d-flex flex-column justify-content-between">
                            <div>
                                <h2 class="h5 mb-2">
                                    <a class="stretched-link text-decoration-none text-reset"
                                        href="{{ route('canvases.edit', $canvas, false) }}">
                                        {{ $canvas->name }}
                                    </a>
                                </h2>
                                <p class="text-body-secondary mb-0">{{ $canvas->width }} x {{ $canvas->height }}</p>
                            </div>

                            <p class="small text-body-tertiary mb-0">
                                {{ __('Updated :time', ['time' => $canvas->updated_at->diffForHumans()]) }}
                            </p>
                        </div>
                    </div>
                </div>
            @endforeach

            @if ($this->can_create)
                <div class="col-md-6 col-xl-3">
                    <x-overlay-trigger
                        class="card h-100 w-100 border border-dashed border-primary bg-primary-subtle text-start text-body-emphasis shadow-none"
                        data-canvas-create-card id="canvas-create-offcanvas" surface="offcanvas">
                        <div class="card-body d-flex flex-column justify-content-between">
                            <div>
                                <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary text-white mb-3"
                                    style="width: 3rem; height: 3rem;">
                                    <span class="fas fa-plus" aria-hidden="true"></span>
                                </div>
                                <h2 class="h5 mb-2">{{ __('Add canvas') }}</h2>
                            </div>
                        </div>
                    </x-overlay-trigger>
                </div>
            @endif
        </div>
    </section>
    <div class="mx-n4 px-4 mx-lg-n6 px-lg-6 bg-body-emphasis py-5 border-y">
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
            <div class="mb-3">
                <h2 class="h3 mb-1">{{ __('Canvas library') }}</h2>
                <p class="text-body-secondary mb-0">
                    {{ __('Browse active and archived canvases for the current team.') }}</p>
            </div>

            <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('Canvas filters') }}">
                @foreach (['active' => __('Active'), 'all' => __('All'), 'archived' => __('Archived')] as $value => $label)
                    <button type="button" @class([
                        'btn',
                        $filter === $value ? 'btn-primary' : 'btn-phoenix-secondary',
                    ]) data-canvas-filter="{{ $value }}"
                        wire:click="setFilter('{{ $value }}')">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>

        <div class="table-responsive-md">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Name') }}</th>
                        <th scope="col">{{ __('Dimensions') }}</th>
                        <th scope="col">{{ __('Last updated') }}</th>
                        <th scope="col" class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->canvases as $canvas)
                        <tr
                            @if (!$canvas->trashed()) x-data
                        x-on:click="window.location = '{{ route('canvases.edit', $canvas, false) }}'"
                        style="cursor: pointer;" @endif>
                            <td>
                                <div class="d-flex flex-column gap-1">
                                    <span class="fw-semibold text-body-emphasis">{{ $canvas->name }}</span>
                                    @if ($canvas->trashed())
                                        <span
                                            class="badge badge-phoenix badge-phoenix-warning align-self-start">{{ __('Archived') }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="text-body-secondary">{{ $canvas->width }} x {{ $canvas->height }}</td>
                            <td class="text-body-secondary">{{ $canvas->updated_at->diffForHumans() }}</td>
                            <td class="text-end">
                                @can($canvas->trashed() ? 'restore' : 'update', $canvas)
                                    <div class="dropdown" x-data x-on:click.stop>
                                        <button type="button" class="btn btn-sm btn-phoenix-secondary"
                                            data-canvas-action-toggle="{{ $canvas->id }}" data-bs-toggle="dropdown"
                                            aria-expanded="false">
                                            <span class="fas fa-ellipsis-h" aria-hidden="true"></span>
                                            <span class="visually-hidden">{{ __('Canvas actions') }}</span>
                                        </button>

                                        <ul class="dropdown-menu dropdown-menu-end py-2">
                                            @if (!$canvas->trashed())
                                                <li>
                                                    <button type="button" class="dropdown-item" data-canvas-action="edit"
                                                        wire:click="openEditOffcanvas({{ $canvas->id }})">
                                                        {{ __('Edit') }}
                                                    </button>
                                                </li>
                                                <li>
                                                    <button type="button" class="dropdown-item text-danger"
                                                        data-canvas-action="archive"
                                                        wire:click="confirmLifecycleAction({{ $canvas->id }}, 'archive')">
                                                        {{ __('Archive') }}
                                                    </button>
                                                </li>
                                            @else
                                                <li>
                                                    <button type="button" class="dropdown-item text-warning-emphasis"
                                                        data-canvas-action="restore"
                                                        wire:click="confirmLifecycleAction({{ $canvas->id }}, 'restore')">
                                                        {{ __('Restore') }}
                                                    </button>
                                                </li>
                                            @endif
                                        </ul>
                                    </div>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-5">
                                <div class="text-center">
                                    <div
                                        class="icon-wrapper icon-wrapper-lg bg-body-highlight rounded-circle mx-auto mb-3">
                                        <span class="uil uil-panorama-h" aria-hidden="true"></span>
                                    </div>
                                    <h3 class="h5 mb-2">{{ __('No canvases here yet') }}</h3>
                                    <p class="text-body-secondary mb-0">
                                        {{ $this->can_create
                                            ? __('Create a canvas to start shaping your overlay workspace.')
                                            : __('No canvases match the current filter for this team.') }}
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
