<?php

namespace Awkw\MapsClient\Data;

final readonly class Marker
{
    /**
     * @param  string  $id  the client's own id for it, unique in the map
     * @param  'small'|'medium'|'large'|null  $size
     * @param  array{0: float|int, 1: float|int}|null  $zoomRange  the zooms it shows at
     */
    public function __construct(
        public string $id,
        public float $longitude,
        public float $latitude,
        public string $label,
        public ?Icon $icon = null,
        public ?string $color = null,
        public ?string $size = null,
        public ?array $zoomRange = null,
        public ?Popover $popover = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'coordinates' => [$this->longitude, $this->latitude],
            'label' => $this->label,
            'icon' => $this->icon?->toArray(),
            'color' => $this->color,
            'size' => $this->size,
            'zoom_range' => $this->zoomRange,
            'popover' => $this->popover?->toArray(),
        ], fn ($value) => $value !== null);
    }
}
