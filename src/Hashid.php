<?php

namespace Awkw\MapsClient;

/** A map's hashid: ten letters and digits, as the service makes them. */
final class Hashid
{
    /** `\z`, not `$`: `$` would also take a trailing newline. */
    public const PATTERN = '/^[A-Za-z0-9]{10}\z/';

    public static function valid(mixed $hashid): bool
    {
        return is_string($hashid) && preg_match(self::PATTERN, $hashid) === 1;
    }

    /**
     * The hashid, checked. A corrupted value such as "../assets" would
     * otherwise reach another endpoint with this key, and an unpublish
     * would take the 404 as success.
     *
     * @throws \InvalidArgumentException
     */
    public static function check(string $hashid): string
    {
        if (! self::valid($hashid)) {
            throw new \InvalidArgumentException('Not a map hashid: '.json_encode($hashid).'.');
        }

        return $hashid;
    }
}
