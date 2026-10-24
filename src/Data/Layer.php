<?php

namespace Awkw\MapsClient\Data;

/**
 * GeoJSON drawn under the markers, inline (up to 1 MB) or uploaded first,
 * with a MapLibre style limited to type, paint, layout and filter.
 */
final readonly class Layer
{
    /**
     * @param  array<string, mixed>|null  $features  a GeoJSON FeatureCollection
     * @param  array{type: 'circle'|'line'|'fill'|'symbol', paint?: array<string, mixed>, layout?: array<string, mixed>, filter?: list<mixed>}  $style
     * @param  array{0: float|int, 1: float|int}|null  $zoomRange
     */
    private function __construct(
        public string $id,
        public ?array $features,
        public ?string $asset,
        public array $style,
        public ?array $zoomRange,
    ) {}

    /**
     * @param  array<string, mixed>  $featureCollection
     * @param  array{type: 'circle'|'line'|'fill'|'symbol', paint?: array<string, mixed>, layout?: array<string, mixed>, filter?: list<mixed>}  $style
     * @param  array{0: float|int, 1: float|int}|null  $zoomRange
     */
    public static function inline(string $id, array $featureCollection, array $style, ?array $zoomRange = null): self
    {
        return new self($id, $featureCollection, null, $style, $zoomRange);
    }

    /**
     * GeoJSON uploaded with Maps::uploadAsset().
     *
     * @param  array{type: 'circle'|'line'|'fill'|'symbol', paint?: array<string, mixed>, layout?: array<string, mixed>, filter?: list<mixed>}  $style
     * @param  array{0: float|int, 1: float|int}|null  $zoomRange
     */
    public static function asset(string $id, string $assetId, array $style, ?array $zoomRange = null): self
    {
        return new self($id, null, $assetId, $style, $zoomRange);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'data' => $this->features ?? ['asset' => (string) $this->asset],
            'style' => $this->style,
            'zoom_range' => $this->zoomRange,
        ], fn ($value) => $value !== null);
    }
}
