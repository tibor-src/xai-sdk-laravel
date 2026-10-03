<?php

declare(strict_types=1);

namespace TiborSrc\XaiSdkLaravel\Tests;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;
use TiborSrc\XaiSdkLaravel\XaiSdkLaravelServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [XaiSdkLaravelServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('xai.api_key', 'test-key');
    }
}
