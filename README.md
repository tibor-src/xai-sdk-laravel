# tibor-src/xai-sdk-laravel

Unofficial Laravel package for [tibor-src/xai-sdk-php](https://github.com/tibor-src/xai-sdk-php).

This package is not published or maintained by xAI. It registers that PHP client's `SpaceXAI` class in the Laravel container. Request and response behavior comes from `tibor-src/xai-sdk-php`.

## Install

```bash
composer require tibor-src/xai-sdk-laravel
```

Requires PHP 8.2 or newer and `tibor-src/xai-sdk-php` 0.2.4 or newer. Set `XAI_API_KEY` in the environment, then publish the config if you want to override the client's constructor options:

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

## xai-sdk-php 0.2.4

The resolved client forwards request bodies to `tibor-src/xai-sdk-php` 0.2.4. That release adds no constructor options, so `config/xai.php`, the facade, and the service provider are unchanged. This package does not validate aspect ratios, guidelines, or model ids.

- `videos->generate()` accepts `aspect_ratio` `21:9` and `5:2`, along with `1:1`, `16:9`, `9:16`, `4:3`, `3:4`, `3:2`, and `2:3`.
- `voice->clientSecrets->create()` accepts the realtime models in `TiborSrc\XaiSdkPhp\KNOWN_REALTIME_MODEL_IDS`: `grok-voice-latest` (which points to `grok-voice-3.0`), `grok-voice-3.0`, `grok-voice-2.0`, and `grok-voice-think-fast-2.0`. A session that asks for `grok-voice-think-fast-2.0` reports `grok-voice-2.0`.
- `responses->create()` accepts `guidelines`. Each entry is `['type' => 'policy', 'category' => ..., 'behavior' => ...]`. `category` is `sexual`, `violence`, `self_harm`, `illicit`, or `profanity`. `behavior` is `decline` for any of those categories, or `offer_support` for `self_harm` only. Each category may appear at most once.
- Live search is removed. The API returns HTTP 410 for `search_parameters` and rejects a tool with `type` `live_search`. Use `webSearch()` and `xSearch()`. `web_search_options` is the chat-completions form of that removal; this client has no chat-completions resource.
- A request may include up to 1000 tools. The client does not enforce that limit.

```php
use TiborSrc\XaiSdkPhp\SpaceXAI;

use function TiborSrc\XaiSdkPhp\webSearch;
use function TiborSrc\XaiSdkPhp\xSearch;

$response = app(SpaceXAI::class)->responses->create([
    'model' => 'grok-4.7',
    'input' => 'Hello',
    'guidelines' => [
        ['type' => 'policy', 'category' => 'violence', 'behavior' => 'decline'],
        ['type' => 'policy', 'category' => 'self_harm', 'behavior' => 'offer_support'],
    ],
    'tools' => [webSearch(), xSearch()],
]);
```

## Tests

```bash
composer test
```

That runs Pint, PHPStan at the maximum level, Pest's type coverage at 100%, and the test suite. Tests mock the PHP client's HTTP layer and do not call the live API.

## License

Apache License 2.0. See `LICENSE` and `NOTICE`.
