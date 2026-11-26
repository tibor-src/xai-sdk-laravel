# Changelog

## [0.2.11.1] - 2026-11-26

Require `tibor-src/xai-sdk-php` `^0.2.11.1`, in line with `@xai-official/sdk` 0.2.11. Packagist locked published `0.2.11` to the v0.2.10 commit, so this package requires the 0.2.11.1 subversion (the installable 0.2.11 port). The previous Laravel release (`v0.2.10`) allowed `^0.2.10`. `^0.2.11` is not used because it would allow that locked Packagist 0.2.11.

Config, the facades, and the service provider are unchanged. `xai-sdk-php` 0.2.11.1 adds no constructor options. These 0.2.11 behaviors are available on the resolved client:

- A request sends `user-agent: xai-sdk/0.2.11.1 (php)` unless `default_headers` already sets `user-agent`.
- `xai-sdk-version` is `php/0.2.11.1` on every request. A value set in `default_headers` is replaced with that.
- `voice->transcribe()` takes `redact` and `show_redacted_text`. `redact` is a list of groups (`pii`, `pci`, `phi`) or single types such as `ssn`, each sent as its own `redact` field. With `show_redacted_text` set to `true`, each redacted word has the original words in `redacted_text`. `KNOWN_TRANSCRIPTION_REDACTIONS` lists the groups this release names.
- `KNOWN_MODEL_IDS` no longer lists `grok-build-0.1`. It reached end of life on November 19, 2026. Any other string is still accepted as a model id.
- Resource methods that take an id (`files`, `batches`, `models`, `responses`, `videos`, and `voice`) throw a `TypeError` for an empty id, `"."`, or `".."` instead of sending the request.
- `responses->create()` without `stream`, and `$stream->done()` on a streaming response, return the completed response when the connection fails or is held open after the terminal event (`response.completed`, `response.failed`, or `response.incomplete`). Later events are not delivered.

Not part of the Laravel layer:

- `SDK_VERSION` lives in `xai-sdk-php`. This package does not set the version string. It is `0.2.11.1` so the installable port is distinct from Packagist's locked 0.2.11.
- Path-parameter validation, transcription redaction encoding, stream tail drain, `KNOWN_MODEL_IDS`, and `KNOWN_TRANSCRIPTION_REDACTIONS` live in `xai-sdk-php`. This package does not parse paths, form fields, or event streams and does not keep its own model list.
- The TypeScript release also stops request timeout and idle timers from keeping a Node.js process alive (`unref()`). PHP has no event loop, and curl timeouts do not keep a CLI process running after a request fails, so that change does not apply here.

## [0.2.10] - 2026-11-21

Require `tibor-src/xai-sdk-php` `^0.2.10`, in line with `@xai-official/sdk` 0.2.10. The previous Laravel release (`v0.2.9`) allowed `^0.2.9`.

Config, the facades, and the service provider are unchanged. `xai-sdk-php` 0.2.10 adds no constructor options. These 0.2.10 behaviors are available on the resolved client:

- A request sends `user-agent: xai-sdk/0.2.10 (php)` unless `default_headers` already sets `user-agent`.
- `xai-sdk-version` is `php/0.2.10` on every request. A value set in `default_headers` is replaced with that.

Not part of the Laravel layer:

- `SDK_VERSION` lives in `xai-sdk-php`. This package does not set the version string.
- The npm package `@grok/sdk`, its release workflow, artifact rename, and package smoke under both names are specific to the TypeScript release. This package is the Composer package `tibor-src/xai-sdk-laravel`.
- The Node.js version error no longer names the package. This package has no runtime version error. Composer enforces `php: ^8.2.0` before the library loads.

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
