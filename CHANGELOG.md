# Changelog

## [0.2.9] - 2026-11-20

Require `tibor-src/xai-sdk-php` `^0.2.9`, in line with `@xai-official/sdk` 0.2.9. The previous Laravel release (`v0.2.8`) allowed `^0.2.8`.

Config, the facades, and the service provider are unchanged. `xai-sdk-php` 0.2.9 adds no constructor options. These 0.2.9 behaviors are available on the resolved client:

- A streamed response keeps the fields of one SSE event together when the server ends lines with `\r\n`. A single `\r\n` is a line break, including when a chunk splits that `\r\n`. `\r` followed by `\r\n`, and `\n` followed by `\r\n`, still end an event. Bare `\r` and `\n` line endings are unchanged.
- `KNOWN_IMAGE_MODEL_IDS` lists `grok-imagine-image` and `grok-imagine-image-2.0`. `grok-imagine-image-quality` was retired on November 2, 2026 and is no longer in that list. Any other string is still accepted as an image model id. Use `grok-imagine-image-2.0` for new work.

Not part of the Laravel layer:

- The SSE parser and `KNOWN_IMAGE_MODEL_IDS` live in `xai-sdk-php`. This package does not parse event streams and does not keep its own model list.
- This package has no validation rules. `images->generate()` forwards the model id as given, including an id that is no longer in the known list.
- `KnownImageModelId` is a TypeScript union. The PHP client accepts any string at runtime, and the Laravel binding does the same.

## [0.2.8] - 2026-11-04

Require `tibor-src/xai-sdk-php` `^0.2.8`, in line with `@xai-official/sdk` 0.2.5 through 0.2.8. The previous Laravel release (`v0.2.4`) allowed `^0.2.4`.

The container now binds `TiborSrc\XaiSdkPhp\SpaceXSI`. `SpaceXAI` stays registered as an alias of that same instance, and `TiborSrc\XaiSdkLaravel\Facades\SpaceXAI` still resolves it. Prefer `SpaceXSI` and `TiborSrc\XaiSdkLaravel\Facades\SpaceXSI`. Client error messages start with `SpaceXSI:`.

These 0.2.5 to 0.2.8 behaviors are available on the resolved client:

- `responses->create()` `guidelines` use `nsfw`, `violence`, `profanity`, and `cyber`. `behavior` is `decline` for the first three and `allow` for `cyber` only. `sexual` is now `nsfw`. `self_harm`, `illicit`, and `offer_support` are gone; the API rejects them with `422`.
- `timeout` and `idle_timeout` from `config/xai.php`, and the `timeout` and `interval` of `videos->wait()` and `batches->wait()`, are capped at 24 hours. `0` or less for `timeout` or `idle_timeout` means 24 hours. A `wait()` timeout of `0` or less still expires at once.
- With `retry_before_output` set, a streamed response that drops after the `200` and before the first event byte is retried. The default remains off.
- `$response->watermarked` and `$stream->watermarked` follow the `x-watermark` header.
- `voice->transcribe()` accepts `webm`, `avi`, and `mov`.
- `incomplete_details.reason` can be `content_filter`. `temperature` and `top_p` are still sent as given.
- `xSearch()` handle lists accept up to 20 handles.

Not part of the Laravel layer:

- This package has no validation rules. The PHP client forwards guidelines, aspect ratios, model ids, transcription formats, sampling values, and handle lists as given.
- The retry itself, the 24-hour cap, watermark detection, and audio-format sniffing live in `xai-sdk-php`. Laravel only forwards `timeout`, `idle_timeout`, and `retry_before_output`.
- `ImageBackground` is in the API spec and no endpoint uses it, so no request or response changed.
- The minimatch pin in the TypeScript SDK is a Node dependency. It does not apply here.
- `web_search_options` is the chat-completions form of the live-search removal. The PHP client has no chat-completions resource, so the Laravel binding does not either.
- The 1000-tool limit and the 20-handle limit are API limits. Neither client enforces them.

## [0.2.4] - 2026-10-17

Require `tibor-src/xai-sdk-php` `^0.2.4`, in line with `@xai-official/sdk` 0.2.4. The previous Laravel release (`v0.2.1.1`) allowed `^0.2.1`.

The container binding still forwards request bodies to the PHP client. These 0.2.4 behaviors are available on the resolved `SpaceXAI` instance:

- `videos->generate()` `aspect_ratio` accepts `21:9` and `5:2`.
- `KNOWN_REALTIME_MODEL_IDS` includes `grok-voice-3.0` and `grok-voice-2.0`. `grok-voice-latest` points to `grok-voice-3.0`. `grok-voice-think-fast-2.0` is still accepted; a session that asks for it reports `grok-voice-2.0`.
- `responses->create()` accepts `guidelines`: per-category instructions for model output. Each entry is `['type' => 'policy', 'category' => ..., 'behavior' => ...]`, where `category` is `sexual`, `violence`, `self_harm`, `illicit`, or `profanity` and `behavior` is `decline` (any category) or `offer_support` (`self_harm` only). Each category may appear at most once.
- Live search is removed at the API. `search_parameters` fails with HTTP 410, and a tool with `type` `live_search` is rejected. Use `webSearch()` and `xSearch()`.
- A request may include up to 1000 tools.

Not part of the Laravel layer:

- Config, the facade, and the service provider are unchanged. `xai-sdk-php` 0.2.4 adds no constructor options.
- This package has no validation rules. The PHP client forwards aspect ratios, guidelines, and model ids as given.
- `web_search_options` is the chat-completions form of the live-search removal. The PHP client has no chat-completions resource, so the Laravel binding does not either.
- The 1000-tool limit is an API limit. Neither client enforces it.
