<?php

namespace Awkw\MapsClient;

use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

/**
 * The maps service said no, or couldn't be reached. Three things say what to
 * do about it:
 *
 * - `retryable`: the same call can be made again safely and may succeed (the
 *   service was busy or did nothing; or the call is safe to repeat, like an
 *   update, and timed out).
 * - `uncertain`: a call that is not safe to repeat (a publish, an upload)
 *   failed in a way that may have succeeded (a timeout, a lost connection,
 *   a 500, 502 or 504, an answer that isn't the API's). Making it again
 *   could make a second map, so fail and report it. `hashid` holds the map
 *   when the service did name it.
 * - `problems`: what to fix, each with the path in the document it is about.
 *
 * Neither flag means the call will fail the same way again: the document
 * breaks a rule, or the key is wrong.
 */
class MapsException extends RuntimeException
{
    /** The service answers these when nothing was done, so any call may be made again. */
    public const NOTHING_DONE = [429, 503];

    /**
     * These may come after the call was done, so only a repeatable call may
     * be made again. A 500 is among them: the service promises nothing about
     * what it stored before failing.
     */
    public const MAYBE_DONE = [500, 502, 504];

    /**
     * @param  list<array{path: string, message: string}>  $problems
     */
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly array $problems = [],
        public readonly bool $retryable = false,
        public readonly bool $uncertain = false,
        ?Throwable $previous = null,
        /** The map a publish made, when its answer named one but wasn't usable otherwise. */
        public readonly ?string $hashid = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    /** @param bool $repeatable whether the call is safe to make twice */
    public static function fromResponse(Response $response, bool $repeatable): self
    {
        $body = $response->json();
        $body = is_array($body) ? $body : [];
        $status = $response->status();
        $maybeDone = in_array($status, self::MAYBE_DONE, true);

        return new self(
            message: "maps: {$status} ".($body['error'] ?? $response->reason()),
            status: $status,
            problems: $body['problems'] ?? [],
            retryable: in_array($status, self::NOTHING_DONE, true) || ($maybeDone && $repeatable),
            uncertain: $maybeDone && ! $repeatable,
        );
    }

    /** @param bool $repeatable whether the call is safe to make twice */
    public static function unreachable(Throwable $previous, bool $repeatable): self
    {
        return new self(
            'maps: the service could not be reached',
            0,
            retryable: $repeatable,
            uncertain: ! $repeatable,
            previous: $previous,
        );
    }

    /**
     * A success whose body isn't what the API answers. The call was done: a
     * repeatable one may be made again for a usable answer, and for one that
     * isn't safe to repeat, what it made is unknown, unless the body names a
     * map, which is kept so the caller can record or unpublish it.
     */
    public static function unexpectedAnswer(Response $response, bool $repeatable): self
    {
        $hashid = $response->json('hashid');

        return new self(
            "maps: {$response->status()} with an answer that isn't the API's",
            $response->status(),
            retryable: $repeatable,
            uncertain: ! $repeatable,
            hashid: Hashid::valid($hashid) ? $hashid : null,
        );
    }
}
