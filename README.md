# tibor-src/xai-sdk-laravel

Unofficial Laravel package for [tibor-src/xai-sdk-php](https://github.com/tibor-src/xai-sdk-php).

This package is not published or maintained by xAI. It registers that PHP client's `SpaceXSI` class in the Laravel container. `SpaceXAI` remains a deprecated alias of the same binding. Request and response behavior comes from `tibor-src/xai-sdk-php`.

## Install

```bash
composer require tibor-src/xai-sdk-laravel
```

Requires PHP 8.2 or newer and `tibor-src/xai-sdk-php` 0.2.8 or newer. Set `XAI_API_KEY` in the environment, then publish the config if you want to override the client's constructor options:

```bash
php artisan vendor:publish --tag=xai-config
```

The published `config/xai.php` reads `XAI_API_KEY`. Leave the other values `null` to keep the PHP client's defaults.

## Usage

```php
use TiborSrc\XaiSdkPhp\SpaceXSI;

$client = app(SpaceXSI::class);

$response = $client->responses->create([
    'model' => 'grok-4.7',
    'input' => 'Explain why the sky is blue in one sentence.',
]);

echo $response->toText();
```

The client sends `store` as `false` unless you opt in. That differs from the API wire default. With storage disabled, it requests encrypted reasoning content so `$response->toInput()` can preserve context between turns. A create call that omits `stream` is sent as a stream and returned as the finished response. Pass `'stream' => false` for one JSON response, or `'stream' => true` for a `ResponseStream`.

`TiborSrc\XaiSdkLaravel\Facades\SpaceXSI` resolves the same container binding. `TiborSrc\XaiSdkLaravel\Facades\SpaceXAI` still resolves that binding and is deprecated. Resources are public properties on the client, so call them on the resolved instance: `SpaceXSI::getFacadeRoot()->responses->create(...)`.

`config/xai.php` passes `timeout` and `idle_timeout` through to the PHP client. Null keeps the client's default. `0`, a negative value, or anything longer than 24 hours becomes 24 hours. The same 24-hour cap applies to `videos->wait()` and `batches->wait()`: a `timeout` or `interval` longer than 24 hours means 24 hours. A `wait()` `timeout` of `0` or less still expires at once, and an `interval` of `0` or less still polls again without a pause.

`retry_before_output` still defaults to off. When it is true, the PHP client retries a dropped connection before the first response byte. That includes a streamed `responses->create()` whose connection drops after the `200` headers and before the first byte of the event stream.

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

## xai-sdk-php 0.2.8

The resolved client forwards request bodies to `tibor-src/xai-sdk-php` 0.2.8. This package does not validate aspect ratios, guidelines, model ids, or transcription formats.

- `responses->create()` accepts `guidelines`. Each entry is `['type' => 'policy', 'category' => ..., 'behavior' => ...]`. `category` is `nsfw`, `violence`, `profanity`, or `cyber`. `behavior` is `decline` for `nsfw`, `violence`, and `profanity`, and `allow` for `cyber` only. The API rejects other pairs with `400`, and it rejects the old `sexual`, `self_harm`, `illicit`, and `offer_support` values with `422`. `cyber` must be enabled for the API key. Each category may appear at most once.
- `$response->watermarked` and `$stream->watermarked` are true when the response has the header `x-watermark: true`. Watermarking is on by default for supported models from November 7, 2026.
- `incomplete_details.reason` can be `content_filter`. `temperature` and `top_p` are sent as given. The API samples `temperature` above 1 at 1, and `top_p` above the model's default at that default.
- `voice->transcribe()` accepts `audio_format` `webm`, `avi`, and `mov`. A `Blob` without a name is sent as `audio.webm`, `audio.avi`, or `audio.mov` when its MIME type or first bytes say so.
- `videos->generate()` accepts `aspect_ratio` `21:9` and `5:2`, along with `1:1`, `16:9`, `9:16`, `4:3`, `3:4`, `3:2`, and `2:3`.
- `voice->clientSecrets->create()` accepts the realtime models in `TiborSrc\XaiSdkPhp\KNOWN_REALTIME_MODEL_IDS`: `grok-voice-latest` (which points to `grok-voice-3.0`), `grok-voice-3.0`, `grok-voice-2.0`, and `grok-voice-think-fast-2.0`. A session that asks for `grok-voice-think-fast-2.0` reports `grok-voice-2.0`.
- Live search is removed. The API returns HTTP 410 for `search_parameters` and rejects a tool with `type` `live_search`. Use `webSearch()` and `xSearch()`. `web_search_options` is the chat-completions form of that removal; this client has no chat-completions resource.
- A request may include up to 1000 tools. `xSearch()` `allowed_x_handles` and `excluded_x_handles` each take up to 20 handles. The client does not enforce either limit.

```php
use TiborSrc\XaiSdkPhp\SpaceXSI;

use function TiborSrc\XaiSdkPhp\webSearch;
use function TiborSrc\XaiSdkPhp\xSearch;

$response = app(SpaceXSI::class)->responses->create([
    'model' => 'grok-4.7',
    'input' => 'Hello',
    'guidelines' => [
        ['type' => 'policy', 'category' => 'violence', 'behavior' => 'decline'],
        ['type' => 'policy', 'category' => 'cyber', 'behavior' => 'allow'],
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
