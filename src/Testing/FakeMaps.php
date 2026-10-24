<?php

namespace Awkw\MapsClient\Testing;

use Awkw\MapsClient\Data\Asset;
use Awkw\MapsClient\Data\MapDocument;
use Awkw\MapsClient\Data\PublishedMap;
use Awkw\MapsClient\Maps;
use Awkw\MapsClient\MapsException;
use Illuminate\Support\Str;
use PHPUnit\Framework\Assert;

/**
 * The maps service in memory, for a client app's tests: FakeMaps::swap()
 * binds it in place of the real client. It keeps what was published and
 * uploaded, answers like the service, and asserts on what happened.
 */
class FakeMaps implements Maps
{
    /** @var array<string, array{document: array<string, mixed>, version: int}> the maps live now */
    public array $maps = [];

    /**
     * Every document published or updated, in order, kept after an unpublish:
     * what the assertions read, so "was it published" doesn't depend on
     * whether it was unpublished since.
     *
     * @var list<array<string, mixed>>
     */
    public array $published = [];

    /** @var array<string, array{contents: string, contentType: string}> */
    public array $assets = [];

    /** @var list<string> */
    public array $unpublished = [];

    public function __construct(protected string $baseUrl = 'https://maps.awkw.org') {}

    public static function swap(): self
    {
        $fake = new self((string) config('maps.url', 'https://maps.awkw.org'));
        app()->instance(Maps::class, $fake);

        return $fake;
    }

    public function publish(MapDocument $document): PublishedMap
    {
        $hashid = Str::random(10);
        $this->maps[$hashid] = ['document' => $document->toArray(), 'version' => 1];
        $this->published[] = $document->toArray();

        return $this->answer($hashid);
    }

    public function update(string $hashid, MapDocument $document): PublishedMap
    {
        if (! isset($this->maps[$hashid])) {
            throw new MapsException("maps: 404 No map {$hashid} of yours.", 404);
        }

        $this->maps[$hashid] = ['document' => $document->toArray(), 'version' => $this->maps[$hashid]['version'] + 1];
        $this->published[] = $document->toArray();

        return $this->answer($hashid);
    }

    public function unpublish(string $hashid): void
    {
        unset($this->maps[$hashid]);
        $this->unpublished[] = $hashid;
    }

    public function get(string $hashid): array
    {
        if (! isset($this->maps[$hashid])) {
            throw new MapsException("maps: 404 No map {$hashid} of yours.", 404);
        }

        return [...$this->maps[$hashid]['document'], 'hashid' => $hashid, 'version' => $this->maps[$hashid]['version']];
    }

    public function uploadAsset(string $contents, string $contentType): Asset
    {
        $id = 'a_'.Str::random(16);
        $this->assets[$id] = ['contents' => $contents, 'contentType' => $contentType];

        return new Asset($id, $contentType === 'application/geo+json' ? 'geojson' : 'image', "{$this->baseUrl}/assets/{$id}");
    }

    /**
     * That a document was published or updated, whether or not it is live now.
     *
     * @param  (callable(array<string, mixed>): bool)|null  $matching  given each document sent
     */
    public function assertPublished(?callable $matching = null): void
    {
        $found = $matching === null ? $this->published : array_filter($this->published, $matching);

        Assert::assertNotEmpty($found, 'No published map matched.');
    }

    /** That nothing was ever published or updated. */
    public function assertNothingPublished(): void
    {
        Assert::assertEmpty($this->published, count($this->published).' document(s) were published or updated.');
    }

    public function assertUnpublished(string $hashid): void
    {
        Assert::assertContains($hashid, $this->unpublished, "Map {$hashid} was not unpublished.");
    }

    protected function answer(string $hashid): PublishedMap
    {
        $version = $this->maps[$hashid]['version'];
        $image = fn (int $width, string $format) => "{$this->baseUrl}/{$hashid}/v{$version}/{$width}.{$format}";

        return new PublishedMap(
            hashid: $hashid,
            version: $version,
            pageUrl: "{$this->baseUrl}/{$hashid}",
            images: array_map(fn (int $width) => ['width' => $width, 'webp' => $image($width, 'webp'), 'jpg' => $image($width, 'jpg')], [400, 800, 1200]),
            embed: '<img class="awkw-maps-embed" data-hashid="'.$hashid.'" src="'.$image(800, 'webp').'" alt="'.e($this->maps[$hashid]['document']['title']).'">',
        );
    }
}
