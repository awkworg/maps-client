<?php

namespace Awkw\MapsClient;

use Awkw\MapsClient\Data\Asset;
use Awkw\MapsClient\Data\MapDocument;
use Awkw\MapsClient\Data\PublishedMap;

/**
 * The maps service, as a client app uses it (docs/api.md in awkworg/maps).
 * Bound to MapsClient; tests swap in Testing\FakeMaps.
 */
interface Maps
{
    /** @throws MapsException */
    public function publish(MapDocument $document): PublishedMap;

    /**
     * Replaces a published map's document; its version goes up.
     *
     * @throws MapsException
     * @throws \InvalidArgumentException when the hashid isn't one
     */
    public function update(string $hashid, MapDocument $document): PublishedMap;

    /**
     * Unpublishes a map: its page and images stop being served. A map that
     * is already gone counts as unpublished.
     *
     * @throws MapsException
     * @throws \InvalidArgumentException when the hashid isn't one
     */
    public function unpublish(string $hashid): void;

    /**
     * The stored map: the document plus the service's fields (hashid,
     * client, version, timestamps, effective_max_zoom).
     *
     * @return array<string, mixed>
     *
     * @throws MapsException
     * @throws \InvalidArgumentException when the hashid isn't one
     */
    public function get(string $hashid): array;

    /**
     * Uploads a PNG, an SVG (stored as a PNG) or GeoJSON for maps to name.
     *
     * @param  'image/png'|'image/svg+xml'|'application/geo+json'  $contentType
     *
     * @throws MapsException
     */
    public function uploadAsset(string $contents, string $contentType): Asset;
}
