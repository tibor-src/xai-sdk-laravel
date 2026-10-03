<?php

declare(strict_types=1);

use TiborSrc\XaiSdkLaravel\Facades\SpaceXAI as SpaceXAIFacade;
use TiborSrc\XaiSdkPhp\Credentials;
use TiborSrc\XaiSdkPhp\Http\HttpRequest;
use TiborSrc\XaiSdkPhp\ModelResponse;
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

it('treats the string false as disabled retry and ignores a non numeric timeout', function () {
    config([
        'xai.timeout' => 'soon',
        'xai.retry_before_output' => 'false',
        'xai.default_headers' => [],
        'xai.fetch' => 'strlen',
    ]);

    $client = $this->app->make(SpaceXAI::class);

    expect($client->timeout)->toBe(Transport::DEFAULT_TIMEOUT_MS)
        ->and($client->retryBeforeOutput)->toBeFalse()
        ->and($client->defaultHeaders)->toBe([])
        ->and($client->fetch)->toBe([Transport::class, 'curl']);
});

it('reads XAI_API_KEY when the config value is empty', function () {
    config(['xai.api_key' => null]);

    withApiKey('env-key', function () {
        expect(Credentials::get($this->app->make(SpaceXAI::class)))->toBe('env-key');
    });
});

it('refuses to build a client when the api key is missing', function () {
    $called = false;

    config([
        'xai.api_key' => '',
        'xai.fetch' => function () use (&$called): never {
            $called = true;

            throw new RuntimeException('live API must not be called');
        },
    ]);

    withApiKey(null, function () use (&$called) {
        expect(fn () => $this->app->make(SpaceXAI::class))
            ->toThrow(RuntimeException::class, 'SpaceXAI: apiKey is missing (set XAI_API_KEY or pass apiKey)');
        expect($called)->toBeFalse();
    });
});

it('rejects config that is not a string keyed array', function () {
    config(['xai' => 'test-key']);

    expect(fn () => $this->app->make(SpaceXAI::class))
        ->toThrow(UnexpectedValueException::class, 'The [xai] config must be an array.');

    config(['xai' => ['api_key' => 'test-key', 0 => 'bad']]);

    expect(fn () => $this->app->make(SpaceXAI::class))
        ->toThrow(UnexpectedValueException::class, 'The [xai] config keys must be strings.');
});

it('creates a response through the mocked client and defaults store to false', function () {
    /** @var list<HttpRequest> $requests */
    $requests = [];

    config(['xai.fetch' => fakeFetch($requests)]);

    $response = $this->app->make(SpaceXAI::class)->responses->create([
        'model' => 'grok-4.7',
        'input' => 'Explain why the sky is blue in one sentence.',
    ]);

    $request = $requests[0] ?? null;
    assert($request instanceof HttpRequest);

    /** @var array<string, mixed> $payload */
    $payload = $request->json();

    expect($response)->toBeInstanceOf(ModelResponse::class)
        ->and($response->toText())->toBe('Hello world')
        ->and($requests)->toHaveCount(1)
        ->and($request->method)->toBe('POST')
        ->and($request->url)->toBe('https://api.x.ai/v1/responses')
        ->and($payload['store'])->toBeFalse()
        ->and($payload['stream'])->toBeTrue()
        ->and($payload['include'])->toBe(['reasoning.encrypted_content'])
        ->and($request->headers->get('authorization'))->toBe('Bearer test-key');
});

it('resolves the facade to the same mocked client', function () {
    /** @var list<HttpRequest> $requests */
    $requests = [];

    config(['xai.fetch' => fakeFetch($requests)]);

    $resolved = SpaceXAIFacade::getFacadeRoot();
    assert($resolved instanceof SpaceXAI);

    $response = $resolved->responses->create([
        'model' => 'grok-4.7',
        'input' => 'Explain why the sky is blue in one sentence.',
        'store' => true,
        'stream' => false,
    ]);

    $request = $requests[0] ?? null;
    assert($request instanceof HttpRequest);

    /** @var array<string, mixed> $payload */
    $payload = $request->json();

    expect($response)->toBeInstanceOf(ModelResponse::class)
        ->and($response->toText())->toBe('Hello world')
        ->and(SpaceXAIFacade::getFacadeRoot())->toBe($this->app->make(SpaceXAI::class))
        ->and($payload['store'])->toBeTrue()
        ->and($payload)->not->toHaveKey('include');
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

    $contents = file_get_contents($target);
    assert(is_string($contents));

    expect($contents)->toContain("env('XAI_API_KEY')");

    unlink($target);
});
