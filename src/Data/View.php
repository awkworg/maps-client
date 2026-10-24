<?php

namespace Awkw\MapsClient\Data;

/** How a map opens. With no centre and zoom, the service fits the markers and layers. */
final readonly class View
{
    /**
     * @param  'light'|'dark'|'white'|'grayscale'|'black'|null  $style
     * @param  array{0: float|int, 1: float|int}|null  $center  [longitude, latitude], with a zoom
     * @param  string|null  $aspectRatio  width:height, as "16:9"
     */
    public function __construct(
        public ?string $style = null,
        public ?array $center = null,
        public float|int|null $zoom = null,
        public float|int|null $minZoom = null,
        public float|int|null $maxZoom = null,
        public ?string $aspectRatio = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'style' => $this->style,
            'center' => $this->center,
            'zoom' => $this->zoom,
            'min_zoom' => $this->minZoom,
            'max_zoom' => $this->maxZoom,
            'aspect_ratio' => $this->aspectRatio,
        ], fn ($value) => $value !== null);
    }
}
