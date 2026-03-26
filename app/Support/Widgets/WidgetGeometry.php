<?php

namespace App\Support\Widgets;

final readonly class WidgetGeometry
{
    /**
     * Default placement offsets for proprietary widgets attached to a canvas.
     */
    public const DEFAULT_ATTACHED_WIDGET_BASE_X = 80;
    public const DEFAULT_ATTACHED_WIDGET_BASE_Y = 80;
    public const DEFAULT_ATTACHED_WIDGET_STEP_X = 32;
    public const DEFAULT_ATTACHED_WIDGET_STEP_Y = 24;
    public const DEFAULT_ATTACHED_WIDGET_MAX_X_PADDING = 120;
    public const DEFAULT_ATTACHED_WIDGET_MAX_Y_PADDING = 90;

    /**
     * Default geometry for remote widgets placed directly on a canvas.
     */
    public const DEFAULT_REMOTE_WIDGET_BASE_X = 120;
    public const DEFAULT_REMOTE_WIDGET_BASE_Y = 120;
    public const DEFAULT_REMOTE_WIDGET_STEP_X = 36;
    public const DEFAULT_REMOTE_WIDGET_STEP_Y = 28;
    public const DEFAULT_REMOTE_WIDGET_WIDTH = 760;
    public const DEFAULT_REMOTE_WIDGET_HEIGHT = 480;
    public const DEFAULT_REMOTE_WIDGET_MAX_X = 920;
    public const DEFAULT_REMOTE_WIDGET_MAX_Y = 520;

    public function __construct(
        public int $positionX,
        public int $positionY,
        public int $width,
        public int $height,
        public int $contentWidth,
        public int $contentHeight,
        public int $cropTop = 0,
        public int $cropRight = 0,
        public int $cropBottom = 0,
        public int $cropLeft = 0,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        return new self(
            positionX: (int) ($input['position_x'] ?? 0),
            positionY: (int) ($input['position_y'] ?? 0),
            width: (int) ($input['width'] ?? 0),
            height: (int) ($input['height'] ?? 0),
            contentWidth: (int) ($input['content_width'] ?? 0),
            contentHeight: (int) ($input['content_height'] ?? 0),
            cropTop: (int) ($input['crop_top'] ?? 0),
            cropRight: (int) ($input['crop_right'] ?? 0),
            cropBottom: (int) ($input['crop_bottom'] ?? 0),
            cropLeft: (int) ($input['crop_left'] ?? 0),
        );
    }

    public static function uncropped(
        int $positionX,
        int $positionY,
        int $width,
        int $height,
        int $contentWidth,
        int $contentHeight,
    ): self {
        return new self(
            positionX: $positionX,
            positionY: $positionY,
            width: $width,
            height: $height,
            contentWidth: $contentWidth,
            contentHeight: $contentHeight,
        );
    }

    /**
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
    public function toArray(): array
    {
        return [
            'position_x' => $this->positionX,
            'position_y' => $this->positionY,
            'width' => $this->width,
            'height' => $this->height,
            'content_width' => $this->contentWidth,
            'content_height' => $this->contentHeight,
            'crop_top' => $this->cropTop,
            'crop_right' => $this->cropRight,
            'crop_bottom' => $this->cropBottom,
            'crop_left' => $this->cropLeft,
        ];
    }

    /**
     * @return array{
     *     frame_width:int,
     *     frame_height:int,
     *     content_width:int,
     *     content_height:int
     * }
     */
    public function editorDefaults(): array
    {
        return [
            'frame_width' => $this->width,
            'frame_height' => $this->height,
            'content_width' => $this->contentWidth,
            'content_height' => $this->contentHeight,
        ];
    }
}
