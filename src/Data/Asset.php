<?php

namespace Awkw\MapsClient\Data;

/** An uploaded file a map can name: an image (icons, popover images, logos) or GeoJSON. */
final readonly class Asset
{
    /** What the API's answer must hold, and the types. */
    public const FIELDS = ['asset' => 'string', 'kind' => 'string', 'url' => 'string'];

    /** @param 'image'|'geojson' $kind */
    public function __construct(
        public string $id,
        public string $kind,
        public string $url,
    ) {}

    /** @param array<string, mixed> $answer */
    public static function fromArray(array $answer): self
    {
        return new self(
            $answer['asset'],
            $answer['kind'] === 'geojson' ? 'geojson' : 'image',
            $answer['url'],
        );
    }
}
