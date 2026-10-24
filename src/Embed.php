<?php

namespace Awkw\MapsClient;

use Awkw\MapsClient\Data\PublishedMap;

/**
 * The code that shows a published map on a page: the service's <img>, which
 * the maps SDK upgrades to the interactive map on click, and the SDK's script
 * tag. Every client shows the same embed code.
 */
final class Embed
{
    /**
     * @param  string|null  $sdkUrl  where maps.js is served; without it, only the <img>
     * @param  string|null  $mapsBase  where the service is, for the SDK's iframe
     */
    public static function html(PublishedMap $map, ?string $sdkUrl = null, ?string $mapsBase = null): string
    {
        if ($sdkUrl === null || $sdkUrl === '') {
            return $map->embed;
        }

        $base = $mapsBase !== null ? ' data-maps-base="'.e(rtrim($mapsBase, '/')).'"' : '';

        return $map->embed."\n".'<script src="'.e($sdkUrl).'"'.$base.' defer></script>';
    }
}
