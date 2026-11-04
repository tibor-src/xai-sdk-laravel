<?php

declare(strict_types=1);

use TiborSrc\XaiSdkLaravel\Facades\SpaceXAI;
use TiborSrc\XaiSdkLaravel\Facades\SpaceXSI;

arch()->preset()->php();

arch()->preset()->security();

// Laravel's Facade contract requires a protected getFacadeAccessor().
arch()->preset()->strict()->ignoring([
    SpaceXAI::class,
    SpaceXSI::class,
]);

arch('package')
    ->expect('TiborSrc\XaiSdkLaravel')
    ->toUseStrictTypes()
    ->not->toUse(['dd', 'ddd', 'dump', 'env', 'exit', 'ray']);
