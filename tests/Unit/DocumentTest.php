<?php

use Awkw\MapsClient\Data\Branding;
use Awkw\MapsClient\Data\Icon;
use Awkw\MapsClient\Data\Layer;
use Awkw\MapsClient\Data\MapDocument;
use Awkw\MapsClient\Data\Marker;
use Awkw\MapsClient\Data\Popover;
use Awkw\MapsClient\Data\View;
use Awkw\MapsClient\Tests\TestCase;

/*
 * Each of the contract's examples, built with the data classes, comes out as
 * the example itself: the documents this package sends are the ones the
 * service's schema was written against.
 */

it('builds the single-place example', function () {
    $document = new MapDocument(
        title: 'Giverny',
        allowedDomains: ['ashgrove.knownart.co', 'manage.known.art'],
        view: new View(style: 'light'),
        markers: [new Marker(
            id: 'place-7',
            longitude: 1.5333,
            latitude: 49.0757,
            label: 'Giverny',
            icon: Icon::builtin('pin'),
            popover: new Popover(
                title: 'Giverny',
                text: 'Village in Normandy.',
                linkUrl: 'https://ashgrove.knownart.co/places/giverny',
                linkLabel: 'Open',
            ),
        )],
    );

    expect($document->toArray())->toEqual(TestCase::example('single-place'));
});

it('builds the place-with-area example', function () {
    $area = TestCase::example('place-with-area')['layers'][0]['data'];

    $document = new MapDocument(
        title: 'Art Institute of Chicago',
        allowedDomains: ['aic.knownart.co'],
        view: new View(style: 'light', aspectRatio: '4:3'),
        markers: [
            new Marker('place-40', -87.6237, 41.8796, 'Art Institute of Chicago', Icon::builtin('museum'), size: 'large', popover: new Popover('Art Institute of Chicago', linkUrl: 'https://aic.knownart.co/places/art-institute')),
            new Marker('place-41', -87.6224, 41.8797, 'Modern Wing', Icon::builtin('building'), size: 'small'),
            new Marker('place-42', -87.6231, 41.8803, 'Ryan Learning Center', Icon::builtin('library'), size: 'small'),
        ],
        layers: [Layer::inline('place-40-area', $area, ['type' => 'fill', 'paint' => ['fill-color' => '#c0392b', 'fill-opacity' => 0.15]])],
    );

    expect($document->toArray())->toEqual(TestCase::example('place-with-area'));
});

it('builds the custom-icon example, with branding overridden', function () {
    $document = new MapDocument(
        title: 'Fairhaven Gallery',
        allowedDomains: ['fairhaven.art', '*.fairhaven.art'],
        view: new View(style: 'dark', center: [-0.1426, 51.5386], zoom: 7, maxZoom: 15),
        markers: [new Marker(
            id: 'place-3',
            longitude: -0.1426,
            latitude: 51.5386,
            label: 'Fairhaven Gallery',
            icon: Icon::asset('a_7f3kQ9xLm2Rt'),
            popover: new Popover('Fairhaven Gallery', 'Open Tuesday to Saturday.', imageAsset: 'a_Pq81ZsN4vWcY'),
        )],
        branding: Branding::defaults()->withLogo('a_Lg0Fairhaven1')->withAttribution('Fairhaven Gallery'),
    );

    expect($document->toArray())->toEqual(TestCase::example('custom-icon'));
});

it('builds the styled-layer example, from an uploaded asset', function () {
    $style = TestCase::example('styled-layer')['layers'][0]['style'];

    $document = new MapDocument(
        title: 'Chicago venues by kind',
        allowedDomains: ['known.art'],
        view: new View(style: 'grayscale', center: [-87.6298, 41.8818], zoom: 11),
        layers: [Layer::asset('venues', 'a_Gj2Venues2026x', $style, zoomRange: [9, 15])],
        cluster: false,
    );

    expect($document->toArray())->toEqual(TestCase::example('styled-layer'));
});

it('builds the clustered example', function () {
    $example = TestCase::example('clustered-markers');

    $document = new MapDocument(
        title: $example['title'],
        allowedDomains: $example['security']['allowed_domains'],
        view: new View(style: 'light', aspectRatio: '16:9'),
        markers: array_map(fn (array $marker) => new Marker(
            id: $marker['id'],
            longitude: $marker['coordinates'][0],
            latitude: $marker['coordinates'][1],
            label: $marker['label'],
            icon: Icon::builtin($marker['icon']['builtin']),
            size: $marker['size'],
        ), $example['markers']),
        cluster: true,
    );

    expect($document->toArray())->toEqual($example);
});

it('removes a part of the default branding with null', function () {
    expect(Branding::defaults()->withoutLogo()->toArray())->toBe(['logo' => null])
        ->and(Branding::defaults()->withoutAttribution()->toArray())->toBe(['attribution' => null])
        ->and(Branding::defaults()->toArray())->toBe([]);
});

it('refuses a built-in icon the service does not ship', function () {
    Icon::builtin('star');
})->throws(InvalidArgumentException::class);
