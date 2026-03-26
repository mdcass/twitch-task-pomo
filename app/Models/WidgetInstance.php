<?php

namespace App\Models;

/**
 * Temporary compatibility alias while the generalized widget model replaces
 * the earlier widget_instances implementation.
 */
class WidgetInstance extends CanvasWidget
{
    protected $table = 'canvas_widgets';
}
