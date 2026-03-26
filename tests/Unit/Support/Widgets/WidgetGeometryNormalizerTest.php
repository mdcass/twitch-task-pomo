<?php

namespace Tests\Unit\Support\Widgets;

use App\Models\Canvas;
use App\Support\Widgets\WidgetGeometry;
use App\Support\Widgets\WidgetGeometryNormalizer;
use PHPUnit\Framework\TestCase;

class WidgetGeometryNormalizerTest extends TestCase
{
    public function test_it_clamps_geometry_to_canvas_bounds_and_normalizes_crop_pairs(): void
    {
        $canvas = new Canvas([
            'width' => 640,
            'height' => 360,
        ]);
        $normalizer = new WidgetGeometryNormalizer();

        $geometry = $normalizer->normalize($canvas, new WidgetGeometry(
            positionX: 900,
            positionY: 900,
            width: 1000,
            height: 50,
            contentWidth: 5000,
            contentHeight: 3,
            cropTop: 10,
            cropRight: 5000,
            cropBottom: 10,
            cropLeft: 5000,
        ));

        $this->assertSame([
            'position_x' => 0,
            'position_y' => 270,
            'width' => 640,
            'height' => 90,
            'content_width' => 3840,
            'content_height' => 3,
            'crop_top' => 0,
            'crop_right' => 0,
            'crop_bottom' => 0,
            'crop_left' => 3720,
        ], $geometry->toArray());
    }

    public function test_it_reduces_crops_proportionally_to_preserve_minimum_visible_content(): void
    {
        $canvas = new Canvas([
            'width' => 800,
            'height' => 600,
        ]);
        $normalizer = new WidgetGeometryNormalizer();

        $geometry = $normalizer->normalize($canvas, new WidgetGeometry(
            positionX: 40,
            positionY: 30,
            width: 500,
            height: 300,
            contentWidth: 400,
            contentHeight: 220,
            cropTop: 80,
            cropRight: 170,
            cropBottom: 70,
            cropLeft: 150,
        ));

        $this->assertSame([
            'position_x' => 40,
            'position_y' => 30,
            'width' => 500,
            'height' => 300,
            'content_width' => 400,
            'content_height' => 220,
            'crop_top' => 70,
            'crop_right' => 148,
            'crop_bottom' => 60,
            'crop_left' => 132,
        ], $geometry->toArray());
    }

    public function test_it_leaves_valid_uncropped_geometry_unchanged(): void
    {
        $canvas = new Canvas([
            'width' => 1920,
            'height' => 1080,
        ]);
        $normalizer = new WidgetGeometryNormalizer();
        $geometry = WidgetGeometry::uncropped(
            positionX: 120,
            positionY: 80,
            width: 640,
            height: 360,
            contentWidth: 640,
            contentHeight: 360,
        );

        $normalized = $normalizer->normalize($canvas, $geometry);

        $this->assertSame($geometry->toArray(), $normalized->toArray());
        $this->assertSame([
            'frame_width' => 640,
            'frame_height' => 360,
            'content_width' => 640,
            'content_height' => 360,
        ], $normalized->editorDefaults());
    }
}
