<?php

use Awkw\MapsClient\Data\MapDocument;
use Awkw\MapsClient\Data\PublishedMap;
use Awkw\MapsClient\Embed;
use Awkw\MapsClient\Maps;
use Awkw\MapsClient\MapsException;
use Awkw\MapsClient\Testing\FakeMaps;
use PHPUnit\Framework\AssertionFailedError;

it('stands in for the service in a client app’s tests', function () {
    $fake = FakeMaps::swap();
    $maps = app(Maps::class);

    $map = $maps->publish(new MapDocument('Giverny', ['known.art']));
    $updated = $maps->update($map->hashid, new MapDocument('Giverny, Eure', ['known.art']));

    expect($maps)->toBe($fake)
        ->and($map->hashid)->toMatch('/^[A-Za-z0-9]{10}$/')
        ->and($updated->version)->toBe(2)
        ->and($maps->get($map->hashid)['title'])->toBe('Giverny, Eure');

    $fake->assertPublished(fn (array $document) => $document['title'] === 'Giverny, Eure');

    $maps->unpublish($map->hashid);
    $fake->assertUnpublished($map->hashid);
    // Still published once, though it isn't live any more.
    $fake->assertPublished(fn (array $document) => $document['title'] === 'Giverny');
    expect($fake->maps)->toBe([]);

    expect(fn () => $maps->update($map->hashid, new MapDocument('x', ['known.art'])))->toThrow(MapsException::class);
});

it('keeps uploads, answering with the asset as the service would', function () {
    $fake = FakeMaps::swap();

    $asset = app(Maps::class)->uploadAsset('{"type":"FeatureCollection","features":[]}', 'application/geo+json');

    expect($asset->kind)->toBe('geojson')
        ->and($asset->url)->toBe("https://maps.example/assets/{$asset->id}")
        ->and($fake->assets)->toHaveKey($asset->id);
});

it('adds the SDK’s script tag to the service’s embed code when it knows where the SDK is', function () {
    $map = new PublishedMap('qeEUqeleyP', 1, 'https://maps.example/qeEUqeleyP', [], '<img class="awkw-maps-embed" data-hashid="qeEUqeleyP">');

    expect(Embed::html($map))->toBe($map->embed)
        ->and(Embed::html($map, 'https://cdn.example/maps.js', 'https://maps.example/'))
        ->toBe($map->embed."\n".'<script src="https://cdn.example/maps.js" data-maps-base="https://maps.example" defer></script>');
});

it('says nothing was published only when nothing was', function () {
    $fake = FakeMaps::swap();
    $fake->assertNothingPublished();

    $map = $fake->publish(new MapDocument('Giverny', ['ashgrove.knownart.co']));
    $fake->unpublish($map->hashid);

    expect(fn () => $fake->assertNothingPublished())->toThrow(AssertionFailedError::class);
});

it('refuses a hashid that is not one, as the real client does', function () {
    $fake = FakeMaps::swap();

    expect(fn () => $fake->unpublish('../assets'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $fake->get("qeEUqeleyP\n"))->toThrow(InvalidArgumentException::class);
});

it('leaves the SDK’s base out when it is empty', function () {
    $map = new PublishedMap('qeEUqeleyP', 1, 'https://maps.example/qeEUqeleyP', [], '<img>');

    expect(Embed::html($map, 'https://cdn.example/maps.js', ''))->toBe("<img>\n".'<script src="https://cdn.example/maps.js" defer></script>');
});
