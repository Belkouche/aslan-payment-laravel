# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## [0.1.0] - 2026-10-22

### Added

- `AslanServiceProvider` (auto-discovered): merges and publishes `config/aslan.php`,
  binds `Aslan\Payment\Aslan` as a lazily built singleton, registers the webhook route.
- `Aslan` facade proxying to the SDK client (`Aslan::client()`, `Aslan::subscriptions()`,
  `Aslan::plans()`, ... and the client's public methods).
- `MissingApiKeyException` thrown when the client is resolved without `ASLAN_API_KEY`.
- `VerifyAslanWebhookSignature` middleware for the `X-Webhook-Signature`
  (`t=<unix>,v1=<hmac_sha256_hex>`) scheme, with constant-time comparison and a
  configurable timestamp tolerance.
- `WebhookController` dispatching `WebhookReceived` plus one typed event per API event name.
- Requires PHP `~8.2.35 || ~8.3.36 || ~8.4.27 || >=8.5.12` (php2shell fixes) and Laravel 12 or 13.
