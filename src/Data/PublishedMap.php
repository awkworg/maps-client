<?php

namespace Awkw\MapsClient\Data;

/** Where a published map is: what Maps::publish() and update() answer. */
final readonly class PublishedMap
{
    /** What the API's answer must hold, as dotted paths. */
    public const FIELDS = ['hashid', 'version', 'urls.page', 'urls.images', 'embed'];

    /**
     * @param  list<array{width: int, webp: string, jpg: string}>  $images
     */
    public function __construct(
        public string $hashid,
        public int $version,
        public string $pageUrl,
        public array $images,
        /** The <img> that shows it; the maps SDK makes it interactive. */
        public string $embed,
    ) {}

    /** @param array<string, mixed> $answer */
    public static function fromArray(array $answer): self
    {
        return new self(
            hashid: (string) $answer['hashid'],
            version: (int) $answer['version'],
            pageUrl: (string) $answer['urls']['page'],
            images: $answer['urls']['images'],
            embed: (string) $answer['embed'],
        );
    }
}
