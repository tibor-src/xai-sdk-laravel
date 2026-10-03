<?php

declare(strict_types=1);

namespace TiborSrc\XaiSdkLaravel\Facades;

use Illuminate\Support\Facades\Facade;
use TiborSrc\XaiSdkPhp\SpaceXAI as SpaceXAIClient;

/**
 * @see SpaceXAIClient
 */
final class SpaceXAI extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SpaceXAIClient::class;
    }
}
