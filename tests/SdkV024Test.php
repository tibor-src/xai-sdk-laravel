<?php

declare(strict_types=1);

use TiborSrc\XaiSdkPhp\Http\HttpRequest;
use TiborSrc\XaiSdkPhp\Http\HttpResponse;
use TiborSrc\XaiSdkPhp\SpaceXSI;

use function TiborSrc\XaiSdkPhp\webSearch;
use function TiborSrc\XaiSdkPhp\xSearch;

it('loads the realtime voice model ids from xai-sdk-php 0.2.8', function () {
    expect(version_compare(\TiborSrc\XaiSdkPhp\SDK_VERSION, '0.2.8', '>='))->toBeTrue()
        ->and(version_compare(\TiborSrc\XaiSdkPhp\SDK_VERSION, '0.3.0', '<'))->toBeTrue()
        ->and(\TiborSrc\XaiSdkPhp\KNOWN_REALTIME_MODEL_IDS)->toBe([
            'grok-voice-latest',
            'grok-voice-3.0',
            'grok-voice-2.0',
            'grok-voice-think-fast-2.0',
        ]);
});

it('posts guidelines and the web and x search tools through the laravel client', function () {
    /** @var list<HttpRequest> $requests */
    $requests = [];
    $guidelines = [
        ['type' => 'policy', 'category' => 'nsfw', 'behavior' => 'decline'],
        ['type' => 'policy', 'category' => 'violence', 'behavior' => 'decline'],
        ['type' => 'policy', 'category' => 'profanity', 'behavior' => 'decline'],
        ['type' => 'policy', 'category' => 'cyber', 'behavior' => 'allow'],
    ];

    config(['xai.fetch' => fetchJson($requests, completedResponse())]);

    $this->app->make(SpaceXSI::class)->responses->create([
        'model' => 'grok-4.7',
        'input' => 'Hello',
        'guidelines' => $guidelines,
        'tools' => [webSearch(), xSearch()],
        'stream' => false,
    ]);

    $request = $requests[0] ?? null;
    assert($request instanceof HttpRequest);

    /** @var array<string, mixed> $payload */
    $payload = $request->json();

    expect($request->url)->toBe('https://api.x.ai/v1/responses')
        ->and($payload['guidelines'])->toBe($guidelines)
        ->and($payload['tools'])->toBe([
            ['type' => 'web_search'],
            ['type' => 'x_search'],
        ])
        ->and($payload['store'])->toBeFalse();
});

it('posts the 21:9 and 5:2 video aspect ratios through the laravel client', function () {
    /** @var list<HttpRequest> $requests */
    $requests = [];

    config(['xai.fetch' => fetchJson($requests, ['request_id' => 'req_1'])]);

    $client = $this->app->make(SpaceXSI::class);
    $ultrawide = ['model' => 'grok-imagine-video-1.5', 'prompt' => 'A desert road', 'aspect_ratio' => '21:9'];
    $panoramic = ['model' => 'grok-imagine-video-1.5', 'prompt' => 'A desert road', 'aspect_ratio' => '5:2'];
    $client->videos->generate($ultrawide);
    $client->videos->generate($panoramic);

    expect($requests)->toHaveCount(2)
        ->and($requests[0]->url)->toBe('https://api.x.ai/v1/videos/generations')
        ->and($requests[0]->json())->toBe($ultrawide)
        ->and($requests[1]->json())->toBe($panoramic);
});

it('posts grok-voice-3.0 and grok-voice-2.0 on a client secret session', function () {
    /** @var list<HttpRequest> $requests */
    $requests = [];

    config(['xai.fetch' => fetchJson($requests, ['value' => 'secret', 'expires_at' => 1])]);

    $client = $this->app->make(SpaceXSI::class);
    $client->voice->clientSecrets->create(['session' => ['model' => 'grok-voice-3.0']]);
    $client->voice->clientSecrets->create(['session' => ['model' => 'grok-voice-2.0']]);

    expect($requests)->toHaveCount(2)
        ->and($requests[0]->url)->toBe('https://api.x.ai/v1/realtime/client_secrets')
        ->and($requests[0]->json())->toBe(['session' => ['model' => 'grok-voice-3.0']])
        ->and($requests[1]->json())->toBe(['session' => ['model' => 'grok-voice-2.0']]);
});

/**
 * @param  list<HttpRequest>  $requests
 * @param  array<string, mixed>  $body
 * @return Closure(HttpRequest): HttpResponse
 */
function fetchJson(array &$requests, array $body): Closure
{
    return function (HttpRequest $request) use (&$requests, $body): HttpResponse {
        $requests[] = $request;

        return jsonResponse($body);
    };
}
