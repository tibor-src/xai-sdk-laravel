# tibor-src/xai-sdk-laravel

Unofficial Laravel package for [tibor-src/xai-sdk-php](https://github.com/tibor-src/xai-sdk-php).

This package is not published or maintained by xAI. It registers that PHP client's `SpaceXAI` class in the Laravel container. Request and response behavior comes from `tibor-src/xai-sdk-php`.

## Install

```bash
composer require tibor-src/xai-sdk-laravel
```

Requires PHP 8.2 or newer. Set `XAI_API_KEY` in the environment, then publish the config if you want to override the client's constructor options:

```bash
php artisan vendor:publish --tag=xai-config
```

The published `config/xai.php` reads `XAI_API_KEY`. Leave the other values `null` to keep the PHP client's defaults.

## Usage

```php
use TiborSrc\XaiSdkPhp\SpaceXAI;

$client = app(SpaceXAI::class);

$response = $client->responses->create([
    'model' => 'grok-4.7',
    'input' => 'Explain why the sky is blue in one sentence.',
]);

echo $response->toText();
```

The client sends `store` as `false` unless you opt in. That differs from the API wire default. With storage disabled, it requests encrypted reasoning content so `$response->toInput()` can preserve context between turns. A create call that omits `stream` is sent as a stream and returned as the finished response. Pass `'stream' => false` for one JSON response, or `'stream' => true` for a `ResponseStream`.

`TiborSrc\XaiSdkLaravel\Facades\SpaceXAI` resolves the same container binding. Resources are public properties on the client, so call them on the resolved instance: `SpaceXAI::getFacadeRoot()->responses->create(...)`.

## Resources

The binding is the PHP client. It exposes:

- `responses` (`create`, `compact`, `get`, `delete`, `inputItems->list`)
- `models` (`list`, `get`, plus `language`, `image`, and `video`)
- `images` (`generate`, `edit`)
- `videos` (`generate`, `edit`, `extend`, `get`, `wait`)
- `files` (`upload`, `list`, `get`, `delete`, `content`, `createPublicUrl`, `revokePublicUrl`)
- `batches` (`create`, `list`, `get`, `cancel`, `results`, `wait`, `requests`)
- `voice` (`speak`, `transcribe`, `list`, `get`, `custom`, `clientSecrets`)
- `tokenizer` (`encode`)
- `account` (`apiKey`)

## Tests

```bash
composer test
```

That runs Pint, PHPStan at the maximum level, Pest's type coverage at 100%, and the test suite. Tests mock the PHP client's HTTP layer and do not call the live API.

## License

Apache License 2.0. See `LICENSE` and `NOTICE`.
