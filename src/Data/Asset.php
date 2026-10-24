<?php

namespace Awkw\MapsClient\Data;

/** An uploaded file a map can name: an image (icons, popover images, logos) or GeoJSON. */
final readonly class Asset
{
    /** What the API's answer must hold. */
    public const FIELDS = ['asset', 'kind', 'url'];

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
            (string) $answer['asset'],
            $answer['kind'] === 'geojson' ? 'geojson' : 'image',
            (string) $answer['url'],
        );
    }
}
