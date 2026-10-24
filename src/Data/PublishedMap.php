<?php

namespace Awkw\MapsClient\Data;

/** Where a published map is: what Maps::publish() and update() answer. */
final readonly class PublishedMap
{
    /** What the API's answer must hold: dotted paths and their types. */
    public const FIELDS = [
        'hashid' => 'hashid',
        'version' => 'int',
        'urls.page' => 'string',
        'urls.images' => 'images',
        'embed' => 'string',
    ];

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
            hashid: $answer['hashid'],
            version: $answer['version'],
            pageUrl: $answer['urls']['page'],
            images: $answer['urls']['images'],
            embed: $answer['embed'],
        );
    }
}
