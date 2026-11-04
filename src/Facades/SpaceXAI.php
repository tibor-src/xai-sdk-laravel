<?php

declare(strict_types=1);

namespace TiborSrc\XaiSdkLaravel\Facades;

use Illuminate\Support\Facades\Facade;
use TiborSrc\XaiSdkPhp\SpaceXAI as SpaceXAIClient;

/**
 * @deprecated 0.2.8 Use SpaceXSI. This facade resolves the same client.
 * @see SpaceXAIClient
 */
final class SpaceXAI extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SpaceXAIClient::class;
    }
}
