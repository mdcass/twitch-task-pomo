@props([
    'utility',
])

<ul class="navbar-nav navbar-nav-icons flex-row">
    <li class="nav-item">
        <div class="theme-control-toggle px-2">
            <input
                class="form-check-input ms-0 theme-control-toggle-input"
                type="checkbox"
                data-theme-control="phoenixTheme"
                value="dark"
                id="themeControlToggle"
            >
            <label
                class="mb-0 theme-control-toggle-label theme-control-toggle-light"
                for="themeControlToggle"
                data-bs-toggle="tooltip"
                data-bs-placement="left"
                data-bs-title="{{ __('Switch theme') }}"
                style="height:32px;width:32px;"
            >
                <span class="icon uil uil-moon fs-8"></span>
            </label>
            <label
                class="mb-0 theme-control-toggle-label theme-control-toggle-dark"
                for="themeControlToggle"
                data-bs-toggle="tooltip"
                data-bs-placement="left"
                data-bs-title="{{ __('Switch theme') }}"
                style="height:32px;width:32px;"
            >
                <span class="icon uil uil-sun fs-8"></span>
            </label>
        </div>
    </li>

    <li class="nav-item dropdown">
        <a
            class="nav-link lh-1 pe-0"
            id="navbarDropdownUser"
            href="#"
            role="button"
            data-bs-toggle="dropdown"
            data-bs-auto-close="outside"
            aria-haspopup="true"
            aria-expanded="false"
            style="min-width: 2.25rem"
        >
            <div class="avatar avatar-l">
                @if ($utility['profile_photo_url'])
                    <img class="rounded-circle" src="{{ $utility['profile_photo_url'] }}" alt="{{ $utility['name'] }}">
                @else
                    <div class="avatar-name rounded-circle">
                        <span>{{ $utility['initials'] }}</span>
                    </div>
                @endif
            </div>
        </a>

        <div class="dropdown-menu dropdown-menu-end navbar-dropdown-caret py-0 dropdown-profile shadow border" aria-labelledby="navbarDropdownUser">
            <div class="card position-relative border-0">
                <div class="card-body p-0">
                    <div class="text-center pt-4 pb-3">
                        <div class="avatar avatar-xl">
                            @if ($utility['profile_photo_url'])
                                <img class="rounded-circle" src="{{ $utility['profile_photo_url'] }}" alt="{{ $utility['name'] }}">
                            @else
                                <div class="avatar-name rounded-circle">
                                    <span>{{ $utility['initials'] }}</span>
                                </div>
                            @endif
                        </div>
                        <h6 class="mt-2 text-body-emphasis">{{ $utility['name'] }}</h6>
                        @if ($utility['current_team_name'])
                            <p class="text-body-tertiary fs-9 mb-0">{{ $utility['current_team_name'] }}</p>
                        @endif
                    </div>
                </div>

                <div class="overflow-auto scrollbar" style="max-height: 16rem;">
                    <ul class="nav d-flex flex-column my-2 pb-1">
                        @foreach ($utility['actions'] as $action)
                            <li class="nav-item">
                                <a class="nav-link px-3 d-block" href="{{ $action['href'] }}">
                                    <span class="{{ $action['icon'] }} me-2 text-body align-bottom"></span>
                                    <span>{{ $action['label'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>

                @if ($utility['team_actions'] !== [])
                    <div class="card-footer p-0 border-top border-translucent">
                        <ul class="nav d-flex flex-column my-2 pb-1">
                            @foreach ($utility['team_actions'] as $action)
                                <li class="nav-item">
                                    <a class="nav-link px-3 d-block" href="{{ $action['href'] }}">
                                        <span class="{{ $action['icon'] }} me-2 text-body align-bottom"></span>
                                        <span>{{ $action['label'] }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($utility['switchable_teams']->count() > 1)
                    <div class="card-footer p-0 border-top border-translucent">
                        <div class="px-3 pt-3 pb-2">
                            <h6 class="mb-0 text-body-emphasis fs-10">{{ __('Switch Teams') }}</h6>
                        </div>

                        <ul class="nav d-flex flex-column mb-0 pb-3">
                            @foreach ($utility['switchable_teams'] as $team)
                                <li class="nav-item">
                                    <x-switchable-team :team="$team" component="shell.team-switch-link" />
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="card-footer p-0 border-top border-translucent">
                    <div class="px-3 py-3">
                        <form method="POST" action="{{ $utility['logout_url'] }}">
                            @csrf

                            <button type="submit" class="btn btn-phoenix-secondary d-flex flex-center w-100">
                                <span class="uil uil-signout me-2"></span>
                                <span>{{ __('Log Out') }}</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </li>
</ul>
