<?php

namespace App\Http\Controllers;

use App\Models\Canvas;
use Illuminate\Contracts\View\View;

class CanvasController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Canvas::class);

        return view('canvases.index');
    }

    public function edit(Canvas $canvas): View
    {
        $this->authorize('view', $canvas);

        return view('canvases.edit', [
            'canvas' => $canvas->load('createdByUser'),
        ]);
    }
}
