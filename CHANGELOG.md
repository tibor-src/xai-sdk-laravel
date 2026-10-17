# Changelog

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
