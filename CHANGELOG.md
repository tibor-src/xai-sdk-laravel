# Changelog

All notable changes to this Laravel package are documented here. Version numbers follow `tibor-src/xai-sdk-php` and `@xai-official/sdk`.

## [0.2.3] - 2026-10-08

### Added

- `service_tier` in `config/xai.php`, read from `XAI_SERVICE_TIER`. An empty value omits `service_tier`. A set value is sent on Responses API calls that do not set their own `service_tier`. `fast` and `priority` are interchangeable.
- Image generation and edits pass `output.upload_urls` through to `tibor-src/xai-sdk-php`.

### Changed

- Require `tibor-src/xai-sdk-php` `^0.2.3`.

## [0.2.1.1] - 2026-10-03

Initial Laravel package for `tibor-src/xai-sdk-php` 0.2.1.
