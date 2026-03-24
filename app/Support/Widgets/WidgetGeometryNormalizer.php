<?php

namespace App\Support\Widgets;

use App\Models\Canvas;

class WidgetGeometryNormalizer
{
    public const MIN_WIDTH = 120;
    public const MIN_HEIGHT = 90;
    private const BASE_MAX_CONTENT_WIDTH = 3840;
    private const BASE_MAX_CONTENT_HEIGHT = 2160;

    /**
     * @return array{
     *     minWidth:int,
     *     minHeight:int,
     *     maxContentWidth:int,
     *     maxContentHeight:int
     * }
     */
    public function geometryLimits(Canvas $canvas): array
    {
        $canvasWidth = max(1, (int) $canvas->width);
        $canvasHeight = max(1, (int) $canvas->height);

        return [
            'minWidth' => min(self::MIN_WIDTH, $canvasWidth),
            'minHeight' => min(self::MIN_HEIGHT, $canvasHeight),
            'maxContentWidth' => max(self::BASE_MAX_CONTENT_WIDTH, $canvasWidth),
            'maxContentHeight' => max(self::BASE_MAX_CONTENT_HEIGHT, $canvasHeight),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     position_x:int,
     *     position_y:int,
     *     width:int,
     *     height:int,
     *     content_width:int,
     *     content_height:int,
     *     crop_top:int,
     *     crop_right:int,
     *     crop_bottom:int,
     *     crop_left:int
     * }
     */
    public function normalize(Canvas $canvas, array $input): array
    {
        $limits = $this->geometryLimits($canvas);
        $canvasWidth = max(1, (int) $canvas->width);
        $canvasHeight = max(1, (int) $canvas->height);
        $width = $this->clampInt($input['width'] ?? 0, $limits['minWidth'], $canvasWidth);
        $height = $this->clampInt($input['height'] ?? 0, $limits['minHeight'], $canvasHeight);
        $contentWidth = $this->clampInt($input['content_width'] ?? 1, 1, $limits['maxContentWidth']);
        $contentHeight = $this->clampInt($input['content_height'] ?? 1, 1, $limits['maxContentHeight']);
        [$cropLeft, $cropRight] = $this->normalizeCropPair(
            $this->clampInt($input['crop_left'] ?? 0, 0, max(0, $contentWidth - 1)),
            $this->clampInt($input['crop_right'] ?? 0, 0, max(0, $contentWidth - 1)),
            $contentWidth,
        );
        [$cropTop, $cropBottom] = $this->normalizeCropPair(
            $this->clampInt($input['crop_top'] ?? 0, 0, max(0, $contentHeight - 1)),
            $this->clampInt($input['crop_bottom'] ?? 0, 0, max(0, $contentHeight - 1)),
            $contentHeight,
        );
        $x = $this->clampInt($input['position_x'] ?? 0, 0, max(0, $canvasWidth - $width));
        $y = $this->clampInt($input['position_y'] ?? 0, 0, max(0, $canvasHeight - $height));

        return [
            'position_x' => $x,
            'position_y' => $y,
            'width' => $width,
            'height' => $height,
            'content_width' => $contentWidth,
            'content_height' => $contentHeight,
            'crop_top' => $cropTop,
            'crop_right' => $cropRight,
            'crop_bottom' => $cropBottom,
            'crop_left' => $cropLeft,
        ];
    }

    private function clampInt(mixed $value, int $min, int $max): int
    {
        return max($min, min($max, (int) round((float) $value)));
    }

    /**
     * @return array{0:int, 1:int}
     */
    private function normalizeCropPair(int $leadingCrop, int $trailingCrop, int $contentSize): array
    {
        $maxCrop = max(0, $contentSize - 1);
        $leadingCrop = max(0, min($maxCrop, $leadingCrop));
        $trailingCrop = max(0, min($maxCrop, $trailingCrop));
        $totalCrop = $leadingCrop + $trailingCrop;

        if ($totalCrop <= $maxCrop) {
            return [$leadingCrop, $trailingCrop];
        }

        $overflow = $totalCrop - $maxCrop;

        if ($trailingCrop >= $leadingCrop) {
            $trailingCrop = max(0, $trailingCrop - $overflow);
        } else {
            $leadingCrop = max(0, $leadingCrop - $overflow);
        }

        $remainingOverflow = ($leadingCrop + $trailingCrop) - $maxCrop;

        if ($remainingOverflow > 0) {
            $trailingCrop = max(0, $trailingCrop - $remainingOverflow);
        }

        return [$leadingCrop, $trailingCrop];
    }
}
