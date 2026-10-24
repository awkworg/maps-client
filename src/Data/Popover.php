<?php

namespace Awkw\MapsClient\Data;

/** What opens when a marker is clicked: fields the service lays out, never HTML. */
final readonly class Popover
{
    public function __construct(
        public string $title,
        public ?string $text = null,
        /** An image uploaded with Maps::uploadAsset(). */
        public ?string $imageAsset = null,
        /** Where clicking through goes, over HTTPS. */
        public ?string $linkUrl = null,
        public ?string $linkLabel = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'title' => $this->title,
            'text' => $this->text,
            'image' => $this->imageAsset !== null ? ['asset' => $this->imageAsset] : null,
            'link' => $this->linkUrl !== null
                ? array_filter(['url' => $this->linkUrl, 'label' => $this->linkLabel], fn ($value) => $value !== null)
                : null,
        ], fn ($value) => $value !== null);
    }
}
