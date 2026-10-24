<?php

namespace Awkw\MapsClient;

use Illuminate\Http\Client\Factory;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class MapsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('maps')
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(Maps::class, fn ($app): Maps => new MapsClient(
            http: $app->make(Factory::class),
            baseUrl: (string) config('maps.url'),
            key: config('maps.key'),
            timeout: (int) config('maps.timeout'),
            retries: (int) config('maps.retries'),
        ));
    }
}
