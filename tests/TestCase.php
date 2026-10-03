<?php

declare(strict_types=1);

namespace TiborSrc\XaiSdkLaravel\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use TiborSrc\XaiSdkLaravel\XaiSdkLaravelServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [XaiSdkLaravelServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('xai.api_key', 'test-key');
    }
}
