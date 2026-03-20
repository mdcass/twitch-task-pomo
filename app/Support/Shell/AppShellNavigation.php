<?php

namespace App\Support\Shell;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Jetstream\Jetstream;

class AppShellNavigation
{
    /**
     * @return array{
     *     sections: array<int, array<string, mixed>>,
     *     top_navigation_items: array<int, array<string, mixed>>,
     *     active_section_key: string|null,
     *     utility: array<string, mixed>
     * }
     */
    public function for(User $user): array
    {
        $sections = collect($this->sectionDefinitions())
            ->map(fn (array $section) => $this->resolveSection($section, $user))
            ->filter()
            ->values()
            ->all();

        $activeSectionKey = collect($sections)->firstWhere('active', true)['key'] ?? ($sections[0]['key'] ?? null);

        return [
            'sections' => $sections,
            'top_navigation_items' => $this->resolveTopNavigationItems($user),
            'active_section_key' => $activeSectionKey,
            'utility' => $this->resolveUtility($user),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function sectionDefinitions(): array
    {
        return [
            [
                'key' => 'workspace',
                'label' => __('Workspace'),
                'items' => [
                    [
                        'key' => 'dashboard',
                        'label' => __('Dashboard'),
                        'route' => 'dashboard',
                        'active' => ['dashboard'],
                        'icon' => 'uil uil-estate',
                    ],
                    [
                        'key' => 'team-settings',
                        'label' => __('Team Settings'),
                        'route' => 'teams.show',
                        'route_params' => fn (User $user): array => [$user->currentTeam?->id],
                        'active' => ['teams.show'],
                        'icon' => 'uil uil-users-alt',
                        'visible' => fn (User $user): bool => Jetstream::hasTeamFeatures() && $user->currentTeam !== null,
                    ],
                    [
                        'key' => 'team-create',
                        'label' => __('Create Team'),
                        'route' => 'teams.create',
                        'active' => ['teams.create'],
                        'icon' => 'uil uil-plus-circle',
                        'visible' => fn (User $user): bool => Jetstream::hasTeamFeatures()
                            && Gate::forUser($user)->check('create', Jetstream::newTeamModel()),
                    ],
                ],
            ],
            [
                'key' => 'account',
                'label' => __('Account'),
                'items' => [
                    [
                        'key' => 'profile',
                        'label' => __('Profile'),
                        'route' => 'profile.show',
                        'active' => ['profile.show'],
                        'icon' => 'uil uil-user',
                    ],
                    [
                        'key' => 'api-tokens',
                        'label' => __('API Tokens'),
                        'route' => 'api-tokens.index',
                        'active' => ['api-tokens.*'],
                        'icon' => 'uil uil-key-skeleton',
                        'visible' => fn (): bool => Jetstream::hasApiFeatures(),
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $section
     * @return array<string, mixed>|null
     */
    protected function resolveSection(array $section, User $user): ?array
    {
        $items = collect($section['items'] ?? [])
            ->map(fn (array $item) => $this->resolveItem($item, $user))
            ->filter()
            ->values()
            ->all();

        if ($items === []) {
            return null;
        }

        return [
            'key' => $section['key'],
            'label' => $section['label'],
            'items' => $items,
            'active' => collect($items)->contains(fn (array $item) => $item['active']),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    protected function resolveItem(array $item, User $user): ?array
    {
        $visible = $item['visible'] ?? null;

        if (is_callable($visible) && ! $visible($user)) {
            return null;
        }

        $children = collect($item['children'] ?? [])
            ->map(fn (array $child) => $this->resolveItem($child, $user))
            ->filter()
            ->values()
            ->all();

        $href = $item['url'] ?? null;

        if (array_key_exists('route', $item)) {
            if (! Route::has($item['route'])) {
                return null;
            }

            $parameters = [];

            if (array_key_exists('route_params', $item)) {
                $parameters = is_callable($item['route_params'])
                    ? $item['route_params']($user)
                    : $item['route_params'];
            }

            $href = route($item['route'], $parameters, false);
        }

        $active = $this->itemMatchesRoute($item) || collect($children)->contains(fn (array $child) => $child['active']);

        return [
            'id' => Str::slug($item['key'] ?? $item['label']),
            'key' => $item['key'] ?? Str::slug($item['label']),
            'label' => $item['label'],
            'href' => $href,
            'active' => $active,
            'icon' => $item['icon'] ?? 'fa-regular fa-circle',
            'children' => $children,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveUtility(User $user): array
    {
        $actions = [
            [
                'label' => __('Profile'),
                'href' => route('profile.show', absolute: false),
                'icon' => 'uil uil-user',
            ],
        ];

        if (Jetstream::hasApiFeatures() && Route::has('api-tokens.index')) {
            $actions[] = [
                'label' => __('API Tokens'),
                'href' => route('api-tokens.index', absolute: false),
                'icon' => 'uil uil-key-skeleton',
            ];
        }

        return [
            'name' => $user->name,
            'initials' => Str::of($user->name)
                ->explode(' ')
                ->filter()
                ->take(2)
                ->map(fn (string $segment) => Str::upper(Str::substr($segment, 0, 1)))
                ->implode(''),
            'current_team_name' => $user->currentTeam?->name,
            'profile_photo_url' => Jetstream::managesProfilePhotos() ? $user->profile_photo_url : null,
            'actions' => $actions,
            'team_actions' => $this->resolveTeamActions($user),
            'switchable_teams' => Jetstream::hasTeamFeatures() ? $user->allTeams()->values() : collect(),
            'logout_url' => route('logout', absolute: false),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function resolveTeamActions(User $user): array
    {
        if (! Jetstream::hasTeamFeatures()) {
            return [];
        }

        $actions = [];

        if ($user->currentTeam !== null) {
            $actions[] = [
                'label' => __('Team Settings'),
                'href' => route('teams.show', [$user->currentTeam->id], false),
                'icon' => 'uil uil-users-alt',
            ];
        }

        if (! Gate::forUser($user)->check('create', Jetstream::newTeamModel())) {
            return $actions;
        }

        $actions[] = [
            'label' => __('Create New Team'),
            'href' => route('teams.create', absolute: false),
            'icon' => 'uil uil-plus-circle',
        ];

        return $actions;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function resolveTopNavigationItems(User $user): array
    {
        $items = [
            [
                'key' => 'dashboard',
                'label' => __('Dashboard'),
                'href' => route('dashboard', absolute: false),
                'active' => request()->routeIs('dashboard'),
                'icon' => 'uil uil-estate',
                'children' => [],
            ],
        ];

        if (Jetstream::hasTeamFeatures() && $user->currentTeam !== null) {
            $items[] = [
                'key' => 'team',
                'label' => __('Team'),
                'href' => route('teams.show', [$user->currentTeam->id], false),
                'active' => request()->routeIs('teams.show'),
                'icon' => 'uil uil-users-alt',
                'children' => [],
            ];
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function itemMatchesRoute(array $item): bool
    {
        return Collection::wrap($item['active'] ?? [])
            ->filter()
            ->contains(fn (string $pattern) => request()->routeIs($pattern));
    }
}
