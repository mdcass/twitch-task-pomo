<?php

namespace App\Http\Controllers;

use App\Models\Widget;
use Illuminate\Contracts\View\View;

class WidgetController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Widget::class);

        return view('widgets.index');
    }

    public function edit(Widget $widget): View
    {
        $this->authorize('view', $widget);

        return view('widgets.edit', [
            'widget' => $widget,
        ]);
    }
}
