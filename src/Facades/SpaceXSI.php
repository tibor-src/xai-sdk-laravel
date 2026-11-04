<?php

declare(strict_types=1);

namespace TiborSrc\XaiSdkLaravel\Facades;

use Illuminate\Support\Facades\Facade;
use TiborSrc\XaiSdkPhp\SpaceXSI as SpaceXSIClient;

/**
 * @see SpaceXSIClient
 */
final class SpaceXSI extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SpaceXSIClient::class;
    }
}
