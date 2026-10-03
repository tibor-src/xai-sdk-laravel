<?php

declare(strict_types=1);

use TiborSrc\XaiSdkLaravel\Facades\SpaceXAI as SpaceXAIFacade;
use TiborSrc\XaiSdkPhp\Credentials;
use TiborSrc\XaiSdkPhp\Http\HttpRequest;
use TiborSrc\XaiSdkPhp\SpaceXAI;
use TiborSrc\XaiSdkPhp\Transport;

it('binds one SpaceXAI client from the published api key', function () {
    $first = $this->app->make(SpaceXAI::class);
    $second = $this->app->make(SpaceXAI::class);

    expect($first)->toBeInstanceOf(SpaceXAI::class)
        ->and($second)->toBe($first)
        ->and(Credentials::get($first))->toBe('test-key')
        ->and($first->baseURL)->toBe(Transport::DEFAULT_BASE_URL)
        ->and($first->timeout)->toBe(Transport::DEFAULT_TIMEOUT_MS)
        ->and($first->idleTimeout)->toBe(Transport::DEFAULT_IDLE_TIMEOUT_MS)
        ->and($first->maxRetries)->toBe(Transport::DEFAULT_MAX_RETRIES)
        ->and($first->retryBeforeOutput)->toBeFalse()
        ->and($first->fetch)->toBe([Transport::class, 'curl']);
});

it('forwards constructor options and leaves the live transport uncalled', function () {
    $called = false;
    config([
        'xai.base_url' => 'https://example.test/v1/',
        'xai.timeout' => '1500',
        'xai.idle_timeout' => 2500,
        'xai.max_response_body_bytes' => 4096,
        'xai.max_retries' => 0,
        'xai.retry_before_output' => 'true',
        'xai.default_headers' => ['x-test-source' => 'laravel'],
        'xai.fetch' => function () use (&$called): never {
            $called = true;
            throw new RuntimeException('live API must not be called');
        },
    ]);

    $client = $this->app->make(SpaceXAI::class);

    expect($called)->toBeFalse()
        ->and($client->baseURL)->toBe('https://example.test/v1')
        ->and($client->timeout)->toBe(1500)
        ->and($client->idleTimeout)->toBe(2500)
        ->and($client->maxResponseBodyBytes)->toBe(4096)
        ->and($client->maxRetries)->toBe(0)
        ->and($client->retryBeforeOutput)->toBeTrue()
        ->and($client->defaultHeaders)->toBe(['x-test-source' => 'laravel']);
});

it('reads XAI_API_KEY when the config value is empty', function () {
    $previous = getenv('XAI_API_KEY');
    putenv('XAI_API_KEY=env-key');
    $_ENV['XAI_API_KEY'] = 'env-key';
    $_SERVER['XAI_API_KEY'] = 'env-key';
    config(['xai.api_key' => null]);

    try {
        expect(Credentials::get($this->app->make(SpaceXAI::class)))->toBe('env-key');
    } finally {
        if (is_string($previous) && $previous !== '') {
            putenv('XAI_API_KEY='.$previous);
            $_ENV['XAI_API_KEY'] = $previous;
            $_SERVER['XAI_API_KEY'] = $previous;
        } else {
            putenv('XAI_API_KEY');
            unset($_ENV['XAI_API_KEY'], $_SERVER['XAI_API_KEY']);
        }
    }
});

it('refuses to build a client when the api key is missing', function () {
    $called = false;
    $previous = getenv('XAI_API_KEY');
    putenv('XAI_API_KEY');
    unset($_ENV['XAI_API_KEY'], $_SERVER['XAI_API_KEY']);
    config([
        'xai.api_key' => '',
        'xai.fetch' => function () use (&$called): never {
            $called = true;
            throw new RuntimeException('live API must not be called');
        },
    ]);

    try {
        expect(fn () => $this->app->make(SpaceXAI::class))
            ->toThrow(RuntimeException::class, 'SpaceXAI: apiKey is missing (set XAI_API_KEY or pass apiKey)');
        expect($called)->toBeFalse();
    } finally {
        if (is_string($previous) && $previous !== '') {
            putenv('XAI_API_KEY='.$previous);
        }
    }
});

it('creates a response through the mocked client and defaults store to false', function () {
    /** @var list<HttpRequest> $requests */
    $requests = [];
    config(['xai.fetch' => fakeFetch($requests)]);

    $response = $this->app->make(SpaceXAI::class)->responses->create([
        'model' => 'grok-4.6',
        'input' => 'Hello',
    ]);

    expect($response->toText())->toBe('Hello world')
        ->and($requests)->toHaveCount(1)
        ->and($requests[0]->method)->toBe('POST')
        ->and($requests[0]->url)->toBe('https://api.x.ai/v1/responses')
        ->and($requests[0]->json()['store'])->toBeFalse()
        ->and($requests[0]->json()['stream'])->toBeTrue()
        ->and($requests[0]->json()['include'])->toBe(['reasoning.encrypted_content'])
        ->and($requests[0]->headers->get('authorization'))->toBe('Bearer test-key');
});

it('resolves the facade to the same mocked client', function () {
    /** @var list<HttpRequest> $requests */
    $requests = [];
    config(['xai.fetch' => fakeFetch($requests)]);

    $response = SpaceXAIFacade::getFacadeRoot()->responses->create([
        'model' => 'grok-4.6',
        'input' => 'Hello',
        'store' => true,
        'stream' => false,
    ]);

    expect($response->toText())->toBe('Hello world')
        ->and(SpaceXAIFacade::getFacadeRoot())->toBe($this->app->make(SpaceXAI::class))
        ->and($requests[0]->json()['store'])->toBeTrue()
        ->and($requests[0]->json())->not->toHaveKey('include');
});

it('publishes config that reads XAI_API_KEY', function () {
    $target = config_path('xai.php');
    if (is_file($target)) {
        unlink($target);
    }

    $this->artisan('vendor:publish', [
        '--tag' => 'xai-config',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect(file_get_contents($target))->toContain("env('XAI_API_KEY')");

    unlink($target);
});
