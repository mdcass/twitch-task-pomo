<?php

namespace App\View\Components;

use App\Support\Shell\AppShellLayout;
use App\Support\Shell\AppShellNavigation;
use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    public string $layout;

    /**
     * @var array<string, mixed>
     */
    public array $layoutDefinition;

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $sections = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $topNavigationItems = [];

    /**
     * @var array<string, mixed>
     */
    public array $utility = [];

    public function __construct(string $layout = 'vertical')
    {
        $this->layout = AppShellLayout::normalize($layout);
        $this->layoutDefinition = AppShellLayout::definition($this->layout);

        if (auth()->check()) {
            $navigation = app(AppShellNavigation::class)->for(auth()->user());

            $this->sections = $navigation['sections'];
            $this->topNavigationItems = $navigation['top_navigation_items'];
            $this->utility = $navigation['utility'];
        }
    }

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.app');
    }
}
