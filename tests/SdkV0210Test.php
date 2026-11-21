<?php

declare(strict_types=1);

use TiborSrc\XaiSdkPhp\Http\HttpRequest;
use TiborSrc\XaiSdkPhp\Http\HttpResponse;
use TiborSrc\XaiSdkPhp\SpaceXSI;

it('loads xai-sdk-php 0.2.10', function () {
    expect(version_compare(\TiborSrc\XaiSdkPhp\SDK_VERSION, '0.2.10', '>='))->toBeTrue()
        ->and(version_compare(\TiborSrc\XaiSdkPhp\SDK_VERSION, '0.3.0', '<'))->toBeTrue()
        ->and(\TiborSrc\XaiSdkPhp\SDK_USER_AGENT)->toBe('xai-sdk/0.2.10 (php)');
});

it('sends the 0.2.10 sdk headers from the resolved client', function () {
    /** @var list<HttpRequest> $requests */
    $requests = [];

    config(['xai.fetch' => function (HttpRequest $request) use (&$requests): HttpResponse {
        $requests[] = $request;

        return jsonResponse(completedResponse());
    }]);

    $this->app->make(SpaceXSI::class)->responses->create([
        'model' => 'grok-4.7',
        'input' => 'hi',
        'stream' => false,
    ]);

    $request = $requests[0] ?? null;
    assert($request instanceof HttpRequest);

    expect($requests)->toHaveCount(1)
        ->and($request->headers->get('user-agent'))->toBe('xai-sdk/0.2.10 (php)')
        ->and($request->headers->get('xai-sdk-version'))->toBe('php/0.2.10');
});

it('keeps a configured user agent and still stamps the php sdk version', function () {
    /** @var list<HttpRequest> $requests */
    $requests = [];

    config([
        'xai.default_headers' => [
            'user-agent' => 'curl/8',
            'xai-sdk-version' => 'python/9.9.9',
        ],
        'xai.fetch' => function (HttpRequest $request) use (&$requests): HttpResponse {
            $requests[] = $request;

            return jsonResponse(completedResponse());
        },
    ]);

    $this->app->make(SpaceXSI::class)->responses->create([
        'model' => 'grok-4.7',
        'input' => 'hi',
        'stream' => false,
    ]);

    $request = $requests[0] ?? null;
    assert($request instanceof HttpRequest);

    expect($requests)->toHaveCount(1)
        ->and($request->headers->get('user-agent'))->toBe('curl/8')
        ->and($request->headers->get('xai-sdk-version'))->toBe('php/0.2.10');
});
