<?php

declare(strict_types=1);

use TiborSrc\XaiSdkPhp\Http\ChunkSource;
use TiborSrc\XaiSdkPhp\Http\HeaderBag;
use TiborSrc\XaiSdkPhp\Http\HttpRequest;
use TiborSrc\XaiSdkPhp\Http\HttpResponse;
use TiborSrc\XaiSdkPhp\Http\StringSource;
use TiborSrc\XaiSdkPhp\ResponseStream;
use TiborSrc\XaiSdkPhp\SpaceXSI;

it('loads xai-sdk-php 0.2.9 without the retired image model id', function () {
    expect(version_compare(\TiborSrc\XaiSdkPhp\SDK_VERSION, '0.2.9', '>='))->toBeTrue()
        ->and(version_compare(\TiborSrc\XaiSdkPhp\SDK_VERSION, '0.3.0', '<'))->toBeTrue()
        ->and(\TiborSrc\XaiSdkPhp\KNOWN_IMAGE_MODEL_IDS)->toBe([
            'grok-imagine-image',
            'grok-imagine-image-2.0',
        ]);
});

it('forwards grok-imagine-image-2.0 and the retired image model id', function () {
    /** @var list<HttpRequest> $requests */
    $requests = [];

    config(['xai.fetch' => function (HttpRequest $request) use (&$requests): HttpResponse {
        $requests[] = $request;

        return jsonResponse(['data' => [['url' => 'https://example.test/cube.png']]]);
    }]);

    $client = $this->app->make(SpaceXSI::class);
    $current = ['model' => 'grok-imagine-image-2.0', 'prompt' => 'A red cube'];
    $retired = ['model' => 'grok-imagine-image-quality', 'prompt' => 'A red cube'];
    $client->images->generate($current);
    $client->images->generate($retired);

    expect($requests)->toHaveCount(2)
        ->and($requests[0]->url)->toBe('https://api.x.ai/v1/images/generations')
        ->and($requests[0]->json())->toBe($current)
        ->and($requests[1]->json())->toBe($retired);
});

it('keeps CRLF fields of one streamed event together', function () {
    config(['xai.fetch' => fn (): HttpResponse => eventStream(crlfEventStream())]);

    $events = streamEvents($this->app->make(SpaceXSI::class));

    expect($events)->toBe([
        ['delta' => 'hi', 'type' => 'response.output_text.delta'],
        ['type' => 'response.completed', 'response' => completedResponse()],
    ]);
});

it('keeps CRLF fields together when the chunks split a CRLF', function () {
    $raw = crlfEventStream();

    foreach ([1, 2, 3, 5, 7] as $size) {
        $this->app->forgetInstance(SpaceXSI::class);
        config(['xai.fetch' => fn (): HttpResponse => eventStream($raw, $size)]);

        expect(streamEvents($this->app->make(SpaceXSI::class)))->toBe([
            ['delta' => 'hi', 'type' => 'response.output_text.delta'],
            ['type' => 'response.completed', 'response' => completedResponse()],
        ]);
    }
});

it('treats CR followed by CRLF and LF followed by CRLF as a blank line', function () {
    $encoded = json_encode(completedResponse(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $raw = 'data: {"type":"response.created","response":{"id":"r1"}}'."\r\r\n"
        .'data: {"type":"response.completed","response":'.$encoded."}\n\r\n";

    config(['xai.fetch' => fn (): HttpResponse => eventStream($raw)]);

    expect(streamEvents($this->app->make(SpaceXSI::class)))->toBe([
        ['type' => 'response.created', 'response' => ['id' => 'r1']],
        ['type' => 'response.completed', 'response' => completedResponse()],
    ]);
});

function crlfEventStream(): string
{
    $encoded = json_encode(completedResponse(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    return implode('', [
        "event: response.output_text.delta\r\ndata: {\"delta\":\"hi\"}\r\n\r\n",
        "data: {\"type\":\"response.completed\",\r\ndata: \"response\":{$encoded}}\r\n\r\n",
    ]);
}

function eventStream(string $raw, ?int $chunkSize = null): HttpResponse
{
    $body = new StringSource($raw);

    if ($chunkSize !== null) {
        $chunks = [];
        $length = strlen($raw);

        for ($offset = 0; $offset < $length; $offset += $chunkSize) {
            $chunks[] = [substr($raw, $offset, $chunkSize), 0];
        }

        $body = new ChunkSource($chunks);
    }

    return new HttpResponse(
        200,
        HeaderBag::from([
            'content-type' => 'text/event-stream',
            'x-request-id' => 'req_test',
        ]),
        '',
        $body,
    );
}

/**
 * @return list<array<string, mixed>>
 */
function streamEvents(SpaceXSI $client): array
{
    $stream = $client->responses->create([
        'model' => 'grok-4.7',
        'input' => 'hi',
        'stream' => true,
    ]);
    assert($stream instanceof ResponseStream);

    $events = [];

    foreach ($stream as $event) {
        assert(is_array($event));
        $events[] = $event;
    }

    expect($stream->done()->toText())->toBe('Hello world');

    return $events;
}
