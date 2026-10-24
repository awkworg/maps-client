<?php

namespace Awkw\MapsClient\Data;

/**
 * A marker's icon: one the service ships, or one uploaded first. Built-ins
 * other than pin are the basemap sprite's own, so they match every style.
 */
final readonly class Icon
{
    public const BUILTINS = ['pin', 'museum', 'artwork', 'theatre', 'library', 'building', 'attraction', 'university', 'park'];

    private function __construct(
        public ?string $builtin,
        public ?string $asset,
    ) {}

    public static function builtin(string $name): self
    {
        if (! in_array($name, self::BUILTINS, true)) {
            throw new \InvalidArgumentException("No built-in icon named {$name}.");
        }

        return new self($name, null);
    }

    /** An image uploaded with Maps::uploadAsset(). */
    public static function asset(string $assetId): self
    {
        return new self(null, $assetId);
    }

    /** @return array{builtin: string}|array{asset: string} */
    public function toArray(): array
    {
        return $this->builtin !== null ? ['builtin' => $this->builtin] : ['asset' => (string) $this->asset];
    }
}
