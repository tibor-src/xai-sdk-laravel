<?php

declare(strict_types=1);

use TiborSrc\XaiSdkPhp\APIConnectionError;
use TiborSrc\XaiSdkPhp\Http\Blob;
use TiborSrc\XaiSdkPhp\Http\ChunkSource;
use TiborSrc\XaiSdkPhp\Http\FormData;
use TiborSrc\XaiSdkPhp\Http\HeaderBag;
use TiborSrc\XaiSdkPhp\Http\HttpRequest;
use TiborSrc\XaiSdkPhp\Http\HttpResponse;
use TiborSrc\XaiSdkPhp\ModelResponse;
use TiborSrc\XaiSdkPhp\ResponseStream;
use TiborSrc\XaiSdkPhp\SpaceXSI;
use TiborSrc\XaiSdkPhp\TimeoutError;

it('reads x-watermark on a response and on a stream from the laravel client', function () {
    config(['xai.fetch' => fn (): HttpResponse => jsonResponse(completedResponse(), 200, ['x-watermark' => ' true '])]);

    $marked = $this->app->make(SpaceXSI::class)->responses->create([
        'model' => 'grok-4.7',
        'input' => 'hi',
        'stream' => false,
    ]);

    $this->app->forgetInstance(SpaceXSI::class);
    config(['xai.fetch' => fn (): HttpResponse => jsonResponse(completedResponse(), 200, ['x-watermark' => 'false'])]);

    $unmarked = $this->app->make(SpaceXSI::class)->responses->create([
        'model' => 'grok-4.7',
        'input' => 'hi',
        'stream' => false,
    ]);

    $this->app->forgetInstance(SpaceXSI::class);
    config(['xai.fetch' => fn (): HttpResponse => sseResponse([
        ['type' => 'response.completed', 'response' => completedResponse()],
    ], ['x-watermark' => 'TRUE'])]);

    $stream = $this->app->make(SpaceXSI::class)->responses->create([
        'model' => 'grok-4.7',
        'input' => 'hi',
        'stream' => true,
    ]);
    assert($stream instanceof ResponseStream);

    expect($marked)->toBeInstanceOf(ModelResponse::class)
        ->and($marked->watermarked)->toBeTrue()
        ->and($unmarked->watermarked)->toBeFalse()
        ->and(isset($stream->watermarked))->toBeTrue()
        ->and($stream->watermarked)->toBeTrue()
        ->and($stream->done()->watermarked)->toBeTrue();
});

it('forwards temperature and top_p and keeps a content_filter reason', function () {
    /** @var list<HttpRequest> $requests */
    $requests = [];
    $body = completedResponse();
    $body['incomplete_details'] = ['reason' => 'content_filter'];
    $body['temperature'] = 1.5;
    $body['top_p'] = 0.99;

    config(['xai.fetch' => function (HttpRequest $request) use (&$requests, $body): HttpResponse {
        $requests[] = $request;

        return jsonResponse($body);
    }]);

    $response = $this->app->make(SpaceXSI::class)->responses->create([
        'model' => 'grok-4.7',
        'input' => 'hi',
        'temperature' => 1.5,
        'top_p' => 0.99,
        'stream' => false,
    ]);

    $request = $requests[0] ?? null;
    assert($request instanceof HttpRequest);

    /** @var array<string, mixed> $payload */
    $payload = $request->json();

    expect($payload['temperature'])->toBe(1.5)
        ->and($payload['top_p'])->toBe(0.99)
        ->and($response->incomplete_details)->toBe(['reason' => 'content_filter'])
        ->and($response->temperature)->toBe(1.5)
        ->and($response->top_p)->toBe(0.99);
});

it('sends webm, avi, and mov transcriptions and names unnamed blobs', function () {
    /** @var list<HttpRequest> $requests */
    $requests = [];

    config(['xai.fetch' => function (HttpRequest $request) use (&$requests): HttpResponse {
        $requests[] = $request;

        return jsonResponse(['text' => 'hello']);
    }]);

    $bytes = "\xff\xfb\x90\x00";
    $client = $this->app->make(SpaceXSI::class);
    $client->voice->transcribe(['file' => new Blob($bytes, 'audio/webm'), 'audio_format' => 'webm']);
    $client->voice->transcribe(['file' => new Blob($bytes, 'video/x-msvideo'), 'audio_format' => 'avi']);
    $client->voice->transcribe(['file' => new Blob($bytes, 'video/quicktime'), 'audio_format' => 'mov']);
    $client->voice->transcribe(['file' => new Blob($bytes, 'video/avi')]);
    $client->voice->transcribe(['file' => new Blob($bytes, 'video/quicktime')]);
    $client->voice->transcribe(['file' => new Blob('RIFF'."\x00\x00\x00\x00".'AVI ')]);
    $client->voice->transcribe(['file' => new Blob("\x00\x00\x00\x14".'ftypqt  ')]);
    $client->voice->transcribe(['file' => new Blob("\x1a\x45\xdf\xa3".'webm')]);

    expect($requests)->toHaveCount(8);

    $formats = [];
    $names = [];

    foreach ($requests as $request) {
        expect($request->url)->toBe('https://api.x.ai/v1/stt')
            ->and($request->body)->toBeInstanceOf(FormData::class);
        assert($request->body instanceof FormData);
        $formats[] = $request->body->get('audio_format');
        $names[] = formFilename($request->body);
    }

    expect($formats)->toBe(['webm', 'avi', 'mov', null, null, null, null, null])
        ->and($names)->toBe([
            'blob',
            'blob',
            'blob',
            'audio.avi',
            'audio.mov',
            'audio.avi',
            'audio.mov',
            'audio.webm',
        ]);
});

it('caps a video wait and a batch wait at 24 hours and expires a zero wait timeout', function () {
    /** @var list<HttpRequest> $requests */
    $requests = [];

    // A per-attempt timeout of 0 is 24 hours, so it does not shrink the wait deadline below that cap.
    config([
        'xai.timeout' => 0,
        'xai.fetch' => function (HttpRequest $request) use (&$requests): HttpResponse {
            $requests[] = $request;

            if (str_contains($request->url, '/videos/')) {
                return jsonResponse(['status' => 'done', 'request_id' => 'req_1']);
            }

            return jsonResponse([
                'batch_id' => 'batch_1',
                'expire_time' => '2099-01-01',
                'state' => ['num_pending' => 0, 'num_requests' => 1],
            ]);
        }]);

    $client = $this->app->make(SpaceXSI::class);
    $before = microtime(true);
    $video = $client->videos->wait('req_1', ['timeout' => INF, 'interval' => 0]);
    $videoRequest = $requests[0] ?? null;
    assert($videoRequest instanceof HttpRequest);
    assert($videoRequest->signal !== null);

    expect($video->status)->toBe('done')
        ->and($videoRequest->signal->deadline - $before)->toBeGreaterThan(86_000)
        ->and($videoRequest->signal->deadline - $before)->toBeLessThan(86_500);

    $batchBefore = microtime(true);
    $client->batches->wait('batch_1', ['timeout' => 9_999_999_999, 'interval' => 0]);
    $batchRequest = $requests[1] ?? null;
    assert($batchRequest instanceof HttpRequest);
    assert($batchRequest->signal !== null);

    expect($batchRequest->signal->deadline - $batchBefore)->toBeGreaterThan(86_000)
        ->and($batchRequest->signal->deadline - $batchBefore)->toBeLessThan(86_500);

    $this->app->forgetInstance(SpaceXSI::class);
    config(['xai.fetch' => fn (): HttpResponse => jsonResponse([
        'batch_id' => 'batch_1',
        'expire_time' => null,
        'state' => ['num_pending' => 0, 'num_requests' => 0],
    ])]);

    expect(fn () => $this->app->make(SpaceXSI::class)->batches->wait('batch_1', [
        'interval' => 0,
        'timeout' => 0,
    ]))->toThrow(TimeoutError::class, 'Batch batch_1 did not finish within 0ms');
});

it('retries a stream drop before the first byte when retry_before_output is set', function () {
    /** @var list<HttpRequest> $requests */
    $requests = [];

    config([
        'xai.max_retries' => 2,
        'xai.retry_before_output' => true,
        'xai.fetch' => function (HttpRequest $request) use (&$requests): HttpResponse {
            $requests[] = $request;

            if (count($requests) === 1) {
                return new HttpResponse(200, HeaderBag::from([
                    'content-type' => 'text/event-stream',
                    'x-watermark' => 'true',
                    'x-request-id' => 'req_drop',
                ]), '', new ChunkSource([], null, false, new RuntimeException('Connection error'), true));
            }

            return jsonResponse(completedResponse(), 200, ['x-request-id' => 'req_ok']);
        },
    ]);

    $response = $this->app->make(SpaceXSI::class)->responses->create([
        'model' => 'grok-4.7',
        'input' => 'hi',
    ]);

    $first = $requests[0] ?? null;
    $second = $requests[1] ?? null;
    assert($first instanceof HttpRequest);
    assert($second instanceof HttpRequest);

    expect($response->toText())->toBe('Hello world')
        ->and($response->watermarked)->toBeFalse()
        ->and($response->http->requestId)->toBe('req_ok')
        ->and($requests)->toHaveCount(2)
        ->and($first->headers->get('x-client-request-id'))->toBe($second->headers->get('x-client-request-id'));
});

it('does not retry a stream drop before the first byte unless retry_before_output is set', function () {
    /** @var list<HttpRequest> $requests */
    $requests = [];

    config(['xai.fetch' => function (HttpRequest $request) use (&$requests): HttpResponse {
        $requests[] = $request;

        return new HttpResponse(200, HeaderBag::from([
            'content-type' => 'text/event-stream',
            'x-request-id' => 'req_drop',
        ]), '', null);
    }]);

    expect(fn () => $this->app->make(SpaceXSI::class)->responses->create([
        'model' => 'grok-4.7',
        'input' => 'hi',
    ]))->toThrow(APIConnectionError::class, 'SSE response had no body');

    expect($requests)->toHaveCount(1);
});
