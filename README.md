# awkw/maps-client

The Laravel (12 or 13) client for the awkw maps service (`maps.awkw.org`): publish,
update and unpublish maps, upload the icons and GeoJSON they name, and show
them with the embed code. Any of our apps can be a client; Manage is the
first. The API it calls is documented in `awkworg/maps` (`docs/api.md`), and
the documents it sends are that repo's contract (`contract/`).

## Install

A private package, installed from GitHub by tag like `vignage/billing-tenancy`:

```json
"repositories": [{ "type": "vcs", "url": "https://github.com/awkworg/maps-client" }],
"require": { "awkw/maps-client": "^1.0" }
```

Then, in the app's `.env`:

```
MAPS_URL=https://maps.awkw.org
MAPS_KEY=awkw_maps_…          # this app's key; the service keeps only its hash
MAPS_SDK_URL=                 # where maps.js is served, for Embed::html()
```

## Use

```php
use Awkw\MapsClient\Data\{Icon, MapDocument, Marker, Popover, View};
use Awkw\MapsClient\{Embed, Maps, MapsException};

$maps = app(Maps::class);

$document = new MapDocument(
    title: 'Giverny',
    allowedDomains: ['ashgrove.knownart.co'],
    view: new View(style: 'light'),
    markers: [new Marker(
        id: 'place-7',
        longitude: 1.5333,
        latitude: 49.0757,
        label: 'Giverny',
        icon: Icon::builtin('pin'),
        popover: new Popover('Giverny', linkUrl: 'https://ashgrove.knownart.co/places/giverny'),
    )],
);

$map = $maps->publish($document);          // PublishedMap: hashid, version, pageUrl, images, embed
$maps->update($map->hashid, $document);    // the version goes up, and so do the image URLs
$maps->unpublish($map->hashid);            // a map already gone counts as unpublished

echo Embed::html($map, config('maps.sdk_url'), config('maps.url'));
```

Custom icons, popover images, logos and large GeoJSON are uploaded first and
named by id:

```php
$icon = $maps->uploadAsset(file_get_contents('gallery.svg'), 'image/svg+xml');
new Marker('m1', -87.62, 41.88, 'Gallery', Icon::asset($icon->id));
```

## Errors

Every failure is a `MapsException`:

- `status`: the HTTP status, or 0 when the service couldn't be reached.
- `problems`: what to fix, each with the path in the document it's about.
- `retryable`: the same call is safe to make again and could succeed later.
  A queued job can release itself on `retryable`.
- `uncertain`: a publish or upload failed in a way that may have succeeded (a
  timeout, a lost connection). Making it again could make a second map, so
  don't just retry. Find out first whether it landed, for example by keeping
  the hashid only once a publish answers.

When neither is set, the same call will fail the same way: fix the document
or the key.

A hashid that isn't one throws `InvalidArgumentException` before any request
is sent. The client already retries a couple of times when the service says
nothing was done (429, 503). For calls that are safe to repeat (update, unpublish,
get), it also retries when the service couldn't be reached or timed out. A
publish that may have been stored is never sent twice.

## Testing an app that uses it

```php
use Awkw\MapsClient\Testing\FakeMaps;

$fake = FakeMaps::swap();               // binds an in-memory service in place of the real one

// ... the code under test publishes ...

$fake->assertPublished(fn (array $document) => $document['title'] === 'Giverny');
$fake->assertUnpublished($hashid);
```

## Working on it

There's no PHP on the host here, so run Composer, Pest, Pint and PHPStan
through the Sail image:

```bash
docker run --rm -u "$(id -u):$(id -g)" -e COMPOSER_HOME=/tmp/composer \
  -v "$PWD":/app -w /app --entrypoint php sail-8.4/app vendor/bin/pest
```

`tests/fixtures/contract/` is a copy of the contract's examples from
`awkworg/maps`. The data classes are tested to rebuild each of them exactly.
When the contract changes, copy them again.
