<?php

namespace Awkw\MapsClient\Tests;

use Awkw\MapsClient\MapsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [MapsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('maps.url', 'https://maps.example');
        $app['config']->set('maps.key', 'awkw_maps_test_key');
        $app['config']->set('maps.retries', 2);
    }

    /**
     * A contract example, as copied from awkworg/maps (contract/examples).
     *
     * @return array<string, mixed>
     */
    public static function example(string $name): array
    {
        return json_decode((string) file_get_contents(__DIR__."/fixtures/contract/{$name}.json"), true, flags: JSON_THROW_ON_ERROR);
    }
}
