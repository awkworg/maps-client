<?php

use Awkw\MapsClient\Data\MapDocument;
use Awkw\MapsClient\Data\Marker;
use Awkw\MapsClient\Maps;
use Awkw\MapsClient\MapsClient;
use Awkw\MapsClient\MapsException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function giverny(): MapDocument
{
    return new MapDocument('Giverny', ['known.art'], markers: [new Marker('place-7', 1.5333, 49.0757, 'Giverny')]);
}

function published(string $hashid = 'qeEUqeleyP', int $version = 1): array
{
    return [
        'hashid' => $hashid,
        'version' => $version,
        'urls' => [
            'page' => "https://maps.example/{$hashid}",
            'images' => [['width' => 800, 'webp' => "https://maps.example/{$hashid}/v{$version}/800.webp", 'jpg' => "https://maps.example/{$hashid}/v{$version}/800.jpg"]],
        ],
        'embed' => "<img class=\"awkw-maps-embed\" data-hashid=\"{$hashid}\">",
    ];
}

it('is bound from config', function () {
    expect(app(Maps::class))->toBeInstanceOf(MapsClient::class);
});

it('publishes a document with the client key, and says where the map is', function () {
    Http::fake(['maps.example/v1/maps' => Http::response(published(), 201)]);

    $map = app(Maps::class)->publish(giverny());

    expect($map->hashid)->toBe('qeEUqeleyP')
        ->and($map->pageUrl)->toBe('https://maps.example/qeEUqeleyP')
        ->and($map->images[0]['width'])->toBe(800);

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'https://maps.example/v1/maps'
        && $request->hasHeader('Authorization', 'Bearer awkw_maps_test_key')
        && $request['title'] === 'Giverny'
        && $request['contract'] === 2);
});

it('says what to fix when the service refuses a document, and that retrying will not help', function () {
    Http::fake(['maps.example/v1/maps' => Http::response([
        'error' => 'The document breaks rules the service checks.',
        'problems' => [['path' => '/map/min_zoom', 'message' => 'min_zoom (10) is deeper than the basemap goes where this map is (7).']],
    ], 422)]);

    try {
        app(Maps::class)->publish(giverny());
        $this->fail('Expected a MapsException');
    } catch (MapsException $exception) {
        expect($exception->status)->toBe(422)
            ->and($exception->retryable)->toBeFalse()
            ->and($exception->problems[0]['path'])->toBe('/map/min_zoom');
    }

    Http::assertSentCount(1);
});

it('tries a publish again when the service did nothing (503), and succeeds', function () {
    Http::fake(['maps.example/v1/maps' => Http::sequence()
        ->push(['error' => 'coverage could not be read'], 503)
        ->push(published(), 201)]);

    expect(app(Maps::class)->publish(giverny())->hashid)->toBe('qeEUqeleyP');
    Http::assertSentCount(2);
});

it('never sends a publish twice after an error that may have stored it, and says so', function () {
    Http::fake(['maps.example/v1/maps' => Http::response(['error' => 'timed out'], 504)]);

    expect(fn () => app(Maps::class)->publish(giverny()))
        ->toThrow(fn (MapsException $exception) => expect([$exception->retryable, $exception->uncertain])->toBe([false, true]));
    Http::assertSentCount(1);
});

/* A fake that throws records nothing, so these count the attempts themselves. */
function droppingConnections(int &$attempts): void
{
    Http::fake(function () use (&$attempts) {
        $attempts++;

        throw new ConnectionException('connection reset');
    });
}

it('sends a publish once when the connection drops, and marks it uncertain, not retryable', function () {
    $attempts = 0;
    droppingConnections($attempts);

    expect(fn () => app(Maps::class)->publish(giverny()))
        ->toThrow(fn (MapsException $exception) => expect([$exception->status, $exception->retryable, $exception->uncertain])->toBe([0, false, true]));
    expect($attempts)->toBe(1);
});

it('retries a read, an update or an unpublish when the connection drops, and says a retry may help', function (string $call) {
    $attempts = 0;
    droppingConnections($attempts);
    $calls = [
        'get' => fn () => app(Maps::class)->get('qeEUqeleyP'),
        'update' => fn () => app(Maps::class)->update('qeEUqeleyP', giverny()),
        'unpublish' => fn () => app(Maps::class)->unpublish('qeEUqeleyP'),
    ];

    expect($calls[$call])
        ->toThrow(fn (MapsException $exception) => expect([$exception->retryable, $exception->uncertain])->toBe([true, false]));
    expect($attempts)->toBe(3);
})->with(['get', 'update', 'unpublish']);

it('treats a 500 on a publish as maybe stored, and retries an update after one', function () {
    Http::fake([
        'maps.example/v1/maps' => Http::response(['error' => 'Internal error'], 500),
        'maps.example/v1/maps/qeEUqeleyP' => Http::sequence()
            ->push(['error' => 'Internal error'], 500)
            ->push(published(version: 2), 200),
    ]);

    expect(fn () => app(Maps::class)->publish(giverny()))
        ->toThrow(fn (MapsException $exception) => expect([$exception->status, $exception->retryable, $exception->uncertain])->toBe([500, false, true]));
    expect(app(Maps::class)->update('qeEUqeleyP', giverny())->version)->toBe(2);
    Http::assertSentCount(3);
});

it('tries again after a 429', function () {
    Http::fake(['maps.example/v1/maps' => Http::sequence()
        ->push(['error' => 'Too many writes'], 429)
        ->push(published(), 201)]);

    expect(app(Maps::class)->publish(giverny())->hashid)->toBe('qeEUqeleyP');
    Http::assertSentCount(2);
});

it('gives up after its retries, saying a later retry may still help', function () {
    Http::fake(['maps.example/v1/maps' => Http::response(['error' => 'coverage could not be read'], 503)]);

    expect(fn () => app(Maps::class)->publish(giverny()))
        ->toThrow(fn (MapsException $exception) => expect([$exception->status, $exception->retryable])->toBe([503, true]));
    Http::assertSentCount(3);
});

it('retries an unpublish the service did nothing with, and rethrows what is not a 404', function () {
    Http::fake(['maps.example/v1/maps/qeEUqeleyP' => Http::sequence()
        ->push(['error' => 'busy'], 503)
        ->push(null, 204)]);

    app(Maps::class)->unpublish('qeEUqeleyP');
    Http::assertSentCount(2);

    Http::fake(['maps.example/v1/maps/Other00001' => Http::response(['error' => 'broken'], 500)]);
    expect(fn () => app(Maps::class)->unpublish('Other00001'))->toThrow(MapsException::class);
});

it('retries an upload the service did nothing with', function () {
    Http::fake(['maps.example/v1/assets' => Http::sequence()
        ->push(['error' => 'busy'], 503)
        ->push(['asset' => 'a_8COyzWpWO38Ilrqb', 'kind' => 'image', 'url' => 'https://maps.example/assets/a_8COyzWpWO38Ilrqb'], 201)]);

    expect(app(Maps::class)->uploadAsset('png bytes', 'image/png')->id)->toBe('a_8COyzWpWO38Ilrqb');
    Http::assertSentCount(2);
});

it('refuses a hashid that is not one, rather than putting it in a path', function () {
    Http::fake();

    expect(fn () => app(Maps::class)->unpublish('../assets'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(Maps::class)->get(''))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(Maps::class)->unpublish("qeEUqeleyP\n"))->toThrow(InvalidArgumentException::class);
    Http::assertNothingSent();
});

it('raises a MapsException for a success whose body is not the API’s answer', function () {
    Http::fake(['maps.example/v1/maps' => Http::response('', 201)]);

    expect(fn () => app(Maps::class)->publish(giverny()))
        ->toThrow(fn (MapsException $exception) => expect([$exception->retryable, $exception->uncertain])->toBe([false, true]));
});

it('refuses an answer with a field of the wrong type, and lets a repeatable call try again', function (array $answer) {
    Http::fake([
        'maps.example/v1/maps' => Http::response($answer, 201),
        'maps.example/v1/maps/qeEUqeleyP' => Http::response($answer, 200),
    ]);

    expect(fn () => app(Maps::class)->publish(giverny()))
        ->toThrow(fn (MapsException $exception) => expect([$exception->retryable, $exception->uncertain])->toBe([false, true]))
        ->and(fn () => app(Maps::class)->update('qeEUqeleyP', giverny()))
        ->toThrow(fn (MapsException $exception) => expect([$exception->retryable, $exception->uncertain])->toBe([true, false]));
})->with([
    'images not a list' => [['urls' => ['page' => 'x', 'images' => 'x']] + published()],
    'an image missing its jpg' => [['urls' => ['page' => 'x', 'images' => [['width' => 400, 'webp' => 'x']]]] + published()],
    'a hashid that is not one' => [['hashid' => ['qeEUqeleyP']] + published()],
    'a version as a string' => [['version' => '2'] + published()],
]);

it('tries an update again after a timeout, since it is safe to repeat', function () {
    Http::fake(['maps.example/v1/maps/qeEUqeleyP' => Http::sequence()
        ->push(['error' => 'timed out'], 504)
        ->push(published(version: 2), 200)]);

    expect(app(Maps::class)->update('qeEUqeleyP', giverny())->version)->toBe(2);
    Http::assertSentCount(2);
});

it('counts unpublishing a map that is already gone as done', function () {
    Http::fake(['maps.example/v1/maps/*' => Http::response(['error' => 'No map of yours.'], 404)]);

    app(Maps::class)->unpublish('qeEUqeleyP');

    Http::assertSent(fn (Request $request) => $request->method() === 'DELETE');
});

it('uploads an asset as its own body and type', function () {
    Http::fake(['maps.example/v1/assets' => Http::response(['asset' => 'a_8COyzWpWO38Ilrqb', 'kind' => 'image', 'url' => 'https://maps.example/assets/a_8COyzWpWO38Ilrqb'], 201)]);

    $asset = app(Maps::class)->uploadAsset('<svg xmlns="http://www.w3.org/2000/svg"/>', 'image/svg+xml');

    expect($asset->id)->toBe('a_8COyzWpWO38Ilrqb')->and($asset->kind)->toBe('image');
    Http::assertSent(fn (Request $request) => $request->hasHeader('Content-Type', 'image/svg+xml')
        && $request->body() === '<svg xmlns="http://www.w3.org/2000/svg"/>');
});

it('reports an unreachable service as retryable', function () {
    Http::fake(fn () => throw new ConnectionException('connection refused'));

    expect(fn () => app(Maps::class)->get('qeEUqeleyP'))
        ->toThrow(fn (MapsException $exception) => expect([$exception->status, $exception->retryable])->toBe([0, true]));
});

it('refuses to call without a key, rather than sending an anonymous request', function () {
    config(['maps.key' => null]);
    app()->forgetInstance(Maps::class);
    Http::fake();

    expect(fn () => app(Maps::class)->get('qeEUqeleyP'))->toThrow(MapsException::class);
    Http::assertNothingSent();
});
