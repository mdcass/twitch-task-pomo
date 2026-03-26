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

    public function normalize(Canvas $canvas, WidgetGeometry $geometry): WidgetGeometry
    {
        $limits = $this->geometryLimits($canvas);
        $canvasWidth = max(1, (int) $canvas->width);
        $canvasHeight = max(1, (int) $canvas->height);
        $width = $this->clampInt($geometry->width, $limits['minWidth'], $canvasWidth);
        $height = $this->clampInt($geometry->height, $limits['minHeight'], $canvasHeight);
        $contentWidth = $this->clampInt($geometry->contentWidth, 1, $limits['maxContentWidth']);
        $contentHeight = $this->clampInt($geometry->contentHeight, 1, $limits['maxContentHeight']);
        [$cropLeft, $cropRight] = $this->normalizeCropPair(
            $this->clampInt($geometry->cropLeft, 0, max(0, $contentWidth - 1)),
            $this->clampInt($geometry->cropRight, 0, max(0, $contentWidth - 1)),
            $contentWidth,
        );
        [$cropLeft, $cropRight] = $this->enforceMinimumVisibleContent(
            $cropLeft,
            $cropRight,
            $contentWidth,
            $limits['minWidth'],
        );
        [$cropTop, $cropBottom] = $this->normalizeCropPair(
            $this->clampInt($geometry->cropTop, 0, max(0, $contentHeight - 1)),
            $this->clampInt($geometry->cropBottom, 0, max(0, $contentHeight - 1)),
            $contentHeight,
        );
        [$cropTop, $cropBottom] = $this->enforceMinimumVisibleContent(
            $cropTop,
            $cropBottom,
            $contentHeight,
            $limits['minHeight'],
        );
        $x = $this->clampInt($geometry->positionX, 0, max(0, $canvasWidth - $width));
        $y = $this->clampInt($geometry->positionY, 0, max(0, $canvasHeight - $height));

        return new WidgetGeometry(
            positionX: $x,
            positionY: $y,
            width: $width,
            height: $height,
            contentWidth: $contentWidth,
            contentHeight: $contentHeight,
            cropTop: $cropTop,
            cropRight: $cropRight,
            cropBottom: $cropBottom,
            cropLeft: $cropLeft,
        );
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

    /**
     * @return array{0:int, 1:int}
     */
    private function enforceMinimumVisibleContent(
        int $leadingCrop,
        int $trailingCrop,
        int $contentSize,
        int $minimumVisibleSize,
    ): array {
        $minimumVisibleSize = max(1, min($minimumVisibleSize, $contentSize));
        $maximumCrop = max(0, $contentSize - $minimumVisibleSize);
        $totalCrop = $leadingCrop + $trailingCrop;

        if ($totalCrop <= $maximumCrop || $totalCrop === 0) {
            return [$leadingCrop, $trailingCrop];
        }

        $reduction = $totalCrop - $maximumCrop;
        $leadingReduction = intdiv($reduction * $leadingCrop, $totalCrop);
        $trailingReduction = $reduction - $leadingReduction;

        return [
            max(0, $leadingCrop - $leadingReduction),
            max(0, $trailingCrop - $trailingReduction),
        ];
    }
}
