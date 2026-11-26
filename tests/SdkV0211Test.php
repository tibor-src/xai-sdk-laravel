<?php

declare(strict_types=1);

use TiborSrc\XaiSdkPhp\Http\ChunkSource;
use TiborSrc\XaiSdkPhp\Http\FormData;
use TiborSrc\XaiSdkPhp\Http\HeaderBag;
use TiborSrc\XaiSdkPhp\Http\HttpRequest;
use TiborSrc\XaiSdkPhp\Http\HttpResponse;
use TiborSrc\XaiSdkPhp\ResponseStream;
use TiborSrc\XaiSdkPhp\SpaceXSI;

it('loads xai-sdk-php 0.2.11.1 without grok-build-0.1', function () {
    expect(version_compare(\TiborSrc\XaiSdkPhp\SDK_VERSION, '0.2.11.1', '>='))->toBeTrue()
        ->and(version_compare(\TiborSrc\XaiSdkPhp\SDK_VERSION, '0.3.0', '<'))->toBeTrue()
        ->and(\TiborSrc\XaiSdkPhp\SDK_USER_AGENT)->toBe('xai-sdk/0.2.11.1 (php)')
        ->and(\TiborSrc\XaiSdkPhp\KNOWN_MODEL_IDS)->not->toContain('grok-build-0.1')
        ->and(\TiborSrc\XaiSdkPhp\KNOWN_TRANSCRIPTION_REDACTIONS)->toBe(['pii', 'pci', 'phi']);
});

it('sends the 0.2.11.1 sdk headers from the resolved client', function () {
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
        ->and($request->headers->get('user-agent'))->toBe('xai-sdk/0.2.11.1 (php)')
        ->and($request->headers->get('xai-sdk-version'))->toBe('php/0.2.11.1');
});

it('keeps a configured user agent and still stamps the 0.2.11.1 php sdk version', function () {
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
        ->and($request->headers->get('xai-sdk-version'))->toBe('php/0.2.11.1');
});

it('sends each redact value as its own field from the resolved client', function () {
    /** @var list<HttpRequest> $requests */
    $requests = [];
    $redacted = [
        'text' => 'My name is [NAME_1].',
        'language' => 'en',
        'duration' => 1.8,
        'words' => [
            ['text' => 'My', 'start' => 0.1, 'end' => 0.3],
            ['text' => '[NAME_1]', 'start' => 1.1, 'end' => 1.6, 'redacted_text' => 'Ada Lovelace'],
        ],
    ];

    config(['xai.fetch' => function (HttpRequest $request) use (&$requests, $redacted): HttpResponse {
        $requests[] = $request;

        return jsonResponse($redacted);
    }]);

    $res = $this->app->make(SpaceXSI::class)->voice->transcribe([
        'url' => 'https://example.com/call.mp3',
        'language' => 'en',
        'redact' => ['pii', 'pci', 'bank_account'],
        'show_redacted_text' => true,
    ]);

    $request = $requests[0] ?? null;
    assert($request instanceof HttpRequest);
    $body = $request->body;
    expect($body)->toBeInstanceOf(FormData::class);
    assert($body instanceof FormData);

    expect($requests)->toHaveCount(1)
        ->and($request->url)->toBe('https://api.x.ai/v1/stt')
        ->and(array_map(
            static fn ($part): array => [$part->name, $part->value],
            $body->parts,
        ))->toBe([
            ['url', 'https://example.com/call.mp3'],
            ['language', 'en'],
            ['redact', 'pii'],
            ['redact', 'pci'],
            ['redact', 'bank_account'],
            ['show_redacted_text', 'true'],
        ])
        ->and($res->text)->toBe('My name is [NAME_1].')
        ->and($res->words[1]['redacted_text'])->toBe('Ada Lovelace');
});

it('rejects a retargeting id on the resolved client without sending a request', function (callable $call) {
    /** @var list<HttpRequest> $requests */
    $requests = [];

    config(['xai.fetch' => function (HttpRequest $request) use (&$requests): HttpResponse {
        $requests[] = $request;

        return jsonResponse([]);
    }]);

    $client = $this->app->make(SpaceXSI::class);

    foreach (['', '.', '..'] as $bad) {
        expect(fn () => $call($client, $bad))
            ->toThrow(TypeError::class, 'Invalid path parameter: '.json_encode($bad));
    }

    expect($requests)->toHaveCount(0);
})->with([
    'files.get' => [static fn (SpaceXSI $c, string $id) => $c->files->get($id)],
    'batches.get' => [static fn (SpaceXSI $c, string $id) => $c->batches->get($id)],
    'models.get' => [static fn (SpaceXSI $c, string $id) => $c->models->get($id)],
    'responses.get' => [static fn (SpaceXSI $c, string $id) => $c->responses->get($id)],
    'videos.get' => [static fn (SpaceXSI $c, string $id) => $c->videos->get($id)],
    'voice.get' => [static fn (SpaceXSI $c, string $id) => $c->voice->get($id)],
]);

it('keeps the completed response when the connection fails after the terminal event', function (array $terminal) {
    $bytes = sseEventBytes([$terminal]);

    config(['xai.fetch' => fn (): HttpResponse => new HttpResponse(
        200,
        sseStreamHeaders(),
        '',
        new ChunkSource([[$bytes, 0]], null, false, new RuntimeException('terminated: other side closed')),
    )]);

    $response = $this->app->make(SpaceXSI::class)->responses->create([
        'model' => 'grok-4.6',
        'input' => 'hi',
    ]);

    expect($response->status)->toBe($terminal['response']['status'])
        ->and($response->id)->toBe('resp_123');

    $this->app->forgetInstance(SpaceXSI::class);
    config(['xai.fetch' => fn (): HttpResponse => new HttpResponse(
        200,
        sseStreamHeaders(),
        '',
        new ChunkSource([[$bytes, 0]], null, false, new RuntimeException('terminated: other side closed')),
    )]);

    $stream = $this->app->make(SpaceXSI::class)->responses->create([
        'model' => 'grok-4.6',
        'input' => 'hi',
        'stream' => true,
    ]);
    assert($stream instanceof ResponseStream);

    $types = [];
    foreach ($stream as $event) {
        $types[] = $event['type'];
    }

    expect($types)->toBe([$terminal['type']])
        ->and($stream->done()->status)->toBe($terminal['response']['status']);
})->with([
    'completed' => [[
        'type' => 'response.completed',
        'response' => completedResponse(),
    ]],
    'failed' => [[
        'type' => 'response.failed',
        'response' => completedResponse(['status' => 'failed', 'output' => [], 'error' => ['code' => 'server_error', 'message' => 'boom']]),
    ]],
    'incomplete' => [[
        'type' => 'response.incomplete',
        'response' => completedResponse(['status' => 'incomplete', 'incomplete_details' => ['reason' => 'max_output_tokens']]),
    ]],
]);

it('does not deliver later events after the terminal event from the resolved client', function () {
    config(['xai.fetch' => fn (): HttpResponse => sseResponse([
        ['type' => 'response.output_text.delta', 'output_index' => 0, 'content_index' => 0, 'delta' => 'Hello'],
        ['type' => 'response.completed', 'response' => completedResponse()],
        ['type' => 'response.output_text.delta', 'output_index' => 0, 'content_index' => 0, 'delta' => ' again'],
        ['type' => 'error', 'code' => 529, 'message' => 'overloaded'],
    ])]);

    $stream = $this->app->make(SpaceXSI::class)->responses->create([
        'model' => 'grok-4.6',
        'input' => 'hi',
        'stream' => true,
    ]);
    assert($stream instanceof ResponseStream);

    $heard = [];
    $stream->on('text', function (string $text) use (&$heard): void {
        $heard[] = $text;
    })->on('error', function () use (&$heard): void {
        $heard[] = 'error';
    });

    $types = [];
    foreach ($stream as $event) {
        $types[] = $event['type'];
    }

    expect($types)->toBe(['response.output_text.delta', 'response.completed'])
        ->and($heard)->toBe(['Hello'])
        ->and($stream->done()->status)->toBe('completed');
});

/**
 * @param  list<array<string, mixed>>  $events
 */
function sseEventBytes(array $events): string
{
    $raw = '';
    foreach ($events as $event) {
        $raw .= 'data: '.json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n\n";
    }

    return $raw;
}

function sseStreamHeaders(): HeaderBag
{
    return HeaderBag::from([
        'content-type' => 'text/event-stream',
        'x-request-id' => 'req_test',
    ]);
}
