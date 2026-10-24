<?php

namespace Awkw\MapsClient;

use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

/**
 * The maps service said no, or couldn't be reached. `retryable` says whether
 * the same call could succeed later (the service was busy, or its coverage
 * couldn't be read) or will fail the same way (the document breaks a rule,
 * the key is wrong). `problems` says what to fix, each with the path in the
 * document it is about.
 */
class MapsException extends RuntimeException
{
    /** Statuses the service answers when nothing was done and a retry may work. */
    public const RETRYABLE = [429, 502, 503, 504];

    /**
     * @param  list<array{path: string, message: string}>  $problems
     */
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly array $problems = [],
        public readonly bool $retryable = false,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    public static function fromResponse(Response $response): self
    {
        $body = $response->json() ?? [];
        $status = $response->status();

        return new self(
            message: "maps: {$status} ".($body['error'] ?? $response->reason()),
            status: $status,
            problems: $body['problems'] ?? [],
            retryable: in_array($status, self::RETRYABLE, true),
        );
    }

    public static function unreachable(Throwable $previous): self
    {
        return new self('maps: the service could not be reached', 0, retryable: true, previous: $previous);
    }
}
