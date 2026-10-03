<?php

declare(strict_types=1);

use TiborSrc\XaiSdkLaravel\Tests\TestCase;
use TiborSrc\XaiSdkPhp\Http\HeaderBag;
use TiborSrc\XaiSdkPhp\Http\HttpRequest;
use TiborSrc\XaiSdkPhp\Http\HttpResponse;
use TiborSrc\XaiSdkPhp\Http\StringSource;

pest()->extend(TestCase::class)->in(__DIR__);

/**
 * @return array<string, mixed>
 */
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
 * @return Closure(HttpRequest): HttpResponse
 */
function fakeFetch(array &$requests): Closure
{
    return function (HttpRequest $request) use (&$requests): HttpResponse {
        $requests[] = $request;

        return jsonResponse(completedResponse());
    };
}

/**
 * @template T
 *
 * @param  callable(): T  $callback
 * @return T
 */
function withApiKey(?string $key, callable $callback): mixed
{
    $snapshot = [
        'getenv' => getenv('XAI_API_KEY'),
        'env' => $_ENV['XAI_API_KEY'] ?? null,
        'env_set' => array_key_exists('XAI_API_KEY', $_ENV),
        'server' => $_SERVER['XAI_API_KEY'] ?? null,
        'server_set' => array_key_exists('XAI_API_KEY', $_SERVER),
    ];

    if ($key === null) {
        putenv('XAI_API_KEY');
        unset($_ENV['XAI_API_KEY'], $_SERVER['XAI_API_KEY']);
    } else {
        putenv('XAI_API_KEY='.$key);
        $_ENV['XAI_API_KEY'] = $key;
        $_SERVER['XAI_API_KEY'] = $key;
    }

    try {
        return $callback();
    } finally {
        if (is_string($snapshot['getenv']) && $snapshot['getenv'] !== '') {
            putenv('XAI_API_KEY='.$snapshot['getenv']);
        } else {
            putenv('XAI_API_KEY');
        }

        if ($snapshot['env_set'] === true) {
            $_ENV['XAI_API_KEY'] = $snapshot['env'];
        } else {
            unset($_ENV['XAI_API_KEY']);
        }

        if ($snapshot['server_set'] === true) {
            $_SERVER['XAI_API_KEY'] = $snapshot['server'];
        } else {
            unset($_SERVER['XAI_API_KEY']);
        }
    }
}
