<?php

declare(strict_types=1);

use TiborSrc\XaiSdkPhp\Http\HeaderBag;
use TiborSrc\XaiSdkPhp\Http\HttpRequest;
use TiborSrc\XaiSdkPhp\Http\HttpResponse;
use TiborSrc\XaiSdkPhp\Http\StringSource;
use TiborSrc\XaiSdkLaravel\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

function completedResponse(): array
{
    return [
        'id' => 'resp_123',
        'object' => 'response',
        'created_at' => 1754475266,
        'model' => 'grok-4.6',
        'status' => 'completed',
        'store' => false,
        'incomplete_details' => null,
        'output' => [
            [
                'type' => 'message',
                'id' => 'msg_1',
                'role' => 'assistant',
                'status' => 'completed',
                'content' => [['type' => 'output_text', 'text' => 'Hello world', 'annotations' => []]],
            ],
        ],
        'usage' => [
            'input_tokens' => 32,
            'output_tokens' => 9,
            'total_tokens' => 41,
        ],
    ];
}

function jsonResponse(mixed $body, int $status = 200): HttpResponse
{
    $encoded = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    return new HttpResponse(
        $status,
        HeaderBag::from([
            'content-type' => 'application/json',
            'x-request-id' => 'req_test',
        ]),
        '',
        new StringSource($encoded),
    );
}

/**
 * @param  list<HttpRequest>  $requests
 */
function fakeFetch(array &$requests): Closure
{
    return function (HttpRequest $request) use (&$requests): HttpResponse {
        $requests[] = $request;

        return jsonResponse(completedResponse());
    };
}
