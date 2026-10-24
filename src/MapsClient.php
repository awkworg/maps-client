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
 * that are safe to repeat (PUT, DELETE, GET), when it was unreachable or
 * timed out. A publish that may have landed is not sent twice.
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
        return PublishedMap::fromArray(
            $this->send('post', 'v1/maps', $document->toArray(), repeatable: false)->json(),
        );
    }

    public function update(string $hashid, MapDocument $document): PublishedMap
    {
        return PublishedMap::fromArray(
            $this->send('put', "v1/maps/{$hashid}", $document->toArray())->json(),
        );
    }

    public function unpublish(string $hashid): void
    {
        try {
            $this->send('delete', "v1/maps/{$hashid}");
        } catch (MapsException $exception) {
            if ($exception->status !== 404) {
                throw $exception;
            }
        }
    }

    public function get(string $hashid): array
    {
        return $this->send('get', "v1/maps/{$hashid}")->json();
    }

    public function uploadAsset(string $contents, string $contentType): Asset
    {
        $response = $this->call(
            fn (PendingRequest $request) => $request->withBody($contents, $contentType)->post('v1/assets'),
            repeatable: false,
        );

        return Asset::fromArray($response->json());
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

                    return in_array($status, [429, 503], true)
                        || ($repeatable && in_array($status, [502, 504], true));
                },
                throw: false,
            );

        try {
            $response = $make($request);
        } catch (ConnectionException $exception) {
            throw MapsException::unreachable($exception);
        }

        if ($response->failed()) {
            throw MapsException::fromResponse($response);
        }

        return $response;
    }
}
