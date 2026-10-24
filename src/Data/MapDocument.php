<?php

namespace Awkw\MapsClient\Data;

/**
 * A map as the contract (v2) describes it: what Maps::publish() sends. It
 * describes a map, not a client's records; the client maps its own data onto
 * it. The service checks it against the contract's schema and its own rules,
 * and answers with what to fix.
 */
final readonly class MapDocument
{
    public const CONTRACT = 2;

    /**
     * @param  list<string>  $allowedDomains  the sites that may embed it: hosts or *.host, at least one
     * @param  list<Marker>  $markers
     * @param  list<Layer>  $layers
     */
    public function __construct(
        public string $title,
        public array $allowedDomains,
        public View $view = new View,
        public array $markers = [],
        public array $layers = [],
        public ?bool $cluster = null,
        public ?Branding $branding = null,
    ) {}

    /**
     * The document as the API takes it.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $branding = $this->branding?->toArray() ?? [];

        return array_filter([
            'contract' => self::CONTRACT,
            'title' => $this->title,
            'map' => $this->view->toArray(),
            'markers' => array_map(fn (Marker $marker) => $marker->toArray(), $this->markers) ?: null,
            'cluster' => $this->cluster,
            'layers' => array_map(fn (Layer $layer) => $layer->toArray(), $this->layers) ?: null,
            'branding' => $branding ?: null,
            'security' => ['allowed_domains' => $this->allowedDomains],
        ], fn ($value) => $value !== null);
    }
}
