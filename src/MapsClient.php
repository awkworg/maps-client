<?php

namespace Awkw\MapsClient;

use Awkw\MapsClient\Data\Asset;
use Awkw\MapsClient\Data\MapDocument;
use Awkw\MapsClient\Data\PublishedMap;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Throwable;

/**
 * The maps API over HTTP, with this client's key. Calls are retried a few
 * times when the service says nothing was done (429, 503) and, for calls
 * that are safe to repeat (PUT, DELETE, GET), when it was unreachable,
 * timed out, or failed (500, 502, 504). A publish that may have landed is
 * not sent twice.
 */
class MapsClient implements Maps
{
    public function __construct(
        protected Factory $http,
        protected string $baseUrl,
        protected ?string $key,
        protected int $timeout = 10,
        protected int $retries = 2,
    ) {}

    public function publish(MapDocument $document): PublishedMap
    {
        $response = $this->send('post', 'v1/maps', $document->toArray(), repeatable: false);

        return PublishedMap::fromArray($this->answer($response, PublishedMap::FIELDS, repeatable: false));
    }

    public function update(string $hashid, MapDocument $document): PublishedMap
    {
        $response = $this->send('put', $this->mapPath($hashid), $document->toArray());

        return PublishedMap::fromArray($this->answer($response, PublishedMap::FIELDS, repeatable: true));
    }

    public function unpublish(string $hashid): void
    {
        try {
            $this->send('delete', $this->mapPath($hashid));
        } catch (MapsException $exception) {
            if ($exception->status !== 404) {
                throw $exception;
            }
        }
    }

    public function get(string $hashid): array
    {
        return $this->answer($this->send('get', $this->mapPath($hashid)), ['hashid' => 'hashid', 'version' => 'int'], repeatable: true);
    }

    public function uploadAsset(string $contents, string $contentType): Asset
    {
        $response = $this->call(
            fn (PendingRequest $request) => $request->withBody($contents, $contentType)->post('v1/assets'),
            repeatable: false,
        );

        return Asset::fromArray($this->answer($response, Asset::FIELDS, repeatable: false));
    }

    /** A map's path, from a hashid checked to be one (Hashid::check). */
    protected function mapPath(string $hashid): string
    {
        return 'v1/maps/'.rawurlencode(Hashid::check($hashid));
    }

    /**
     * A success's body, checked to hold what the API answers, each field of
     * the type the data classes build from.
     *
     * @param  array<string, 'string'|'int'|'hashid'|'images'>  $fields  dotted paths and their types
     * @return array<string, mixed>
     */
    protected function answer(Response $response, array $fields, bool $repeatable): array
    {
        $body = $response->json();

        if (! is_array($body)) {
            throw MapsException::unexpectedAnswer($response, $repeatable);
        }

        foreach ($fields as $field => $type) {
            if (! self::isOfType(data_get($body, $field), $type)) {
                throw MapsException::unexpectedAnswer($response, $repeatable);
            }
        }

        return $body;
    }

    /** @param 'string'|'int'|'hashid'|'images' $type */
    protected static function isOfType(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'int' => is_int($value),
            'hashid' => Hashid::valid($value),
            'images' => is_array($value) && array_is_list($value) && array_filter(
                $value,
                fn (mixed $image) => ! is_array($image) || ! is_int($image['width'] ?? null)
                    || ! is_string($image['webp'] ?? null) || ! is_string($image['jpg'] ?? null),
            ) === [],
        };
    }

    /** @param array<string, mixed>|null $body */
    protected function send(string $method, string $path, ?array $body = null, bool $repeatable = true): Response
    {
        return $this->call(
            fn (PendingRequest $request) => $body === null
                ? $request->{$method}($path)
                : $request->{$method}($path, $body),
            $repeatable,
        );
    }

    /** @param callable(PendingRequest): Response $make */
    protected function call(callable $make, bool $repeatable): Response
    {
        if ($this->key === null || $this->key === '') {
            throw new MapsException('maps: no client key is configured (MAPS_KEY)', 401);
        }

        $request = $this->http
            ->baseUrl(rtrim($this->baseUrl, '/'))
            ->withToken($this->key)
            ->acceptJson()
            ->timeout($this->timeout)
            ->retry(
                $this->retries + 1,
                fn (int $attempt) => 200 * $attempt,
                function (Throwable $exception) use ($repeatable): bool {
                    if ($exception instanceof ConnectionException) {
                        return $repeatable;
                    }

                    $status = $exception instanceof RequestException ? $exception->response->status() : null;

                    return in_array($status, MapsException::NOTHING_DONE, true)
                        || ($repeatable && in_array($status, MapsException::MAYBE_DONE, true));
                },
                throw: false,
            );

        try {
            $response = $make($request);
        } catch (ConnectionException $exception) {
            throw MapsException::unreachable($exception, $repeatable);
        }

        if ($response->failed()) {
            throw MapsException::fromResponse($response, $repeatable);
        }

        return $response;
    }
}
