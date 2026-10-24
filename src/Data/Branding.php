<?php

namespace Awkw\MapsClient\Data;

/**
 * Overrides the client's default branding, which the service keeps. Each
 * part has three states: left as the default (the default here), replaced,
 * or removed for this map.
 */
final readonly class Branding
{
    private function __construct(
        public ?string $logoAsset,
        public bool $removeLogo,
        public ?string $attribution,
        public bool $removeAttribution,
    ) {}

    public static function defaults(): self
    {
        return new self(null, false, null, false);
    }

    /** A logo uploaded with Maps::uploadAsset(). */
    public function withLogo(string $assetId): self
    {
        return new self($assetId, false, $this->attribution, $this->removeAttribution);
    }

    public function withoutLogo(): self
    {
        return new self(null, true, $this->attribution, $this->removeAttribution);
    }

    public function withAttribution(string $attribution): self
    {
        return new self($this->logoAsset, $this->removeLogo, $attribution, false);
    }

    public function withoutAttribution(): self
    {
        return new self($this->logoAsset, $this->removeLogo, null, true);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $branding = [];

        if ($this->logoAsset !== null || $this->removeLogo) {
            $branding['logo'] = $this->logoAsset !== null ? ['asset' => $this->logoAsset] : null;
        }

        if ($this->attribution !== null || $this->removeAttribution) {
            $branding['attribution'] = $this->attribution;
        }

        return $branding;
    }
}
