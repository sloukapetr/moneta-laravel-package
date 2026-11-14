# Changelog

All notable changes to this project will be documented in this file.

The format is based on Keep a Changelog.

## [Unreleased]

## [1.2.0] - 2026-11-14

### Added

- GitHub Actions CI for PHP 8.2–8.5 with Laravel 11, 12 and 13.
- Release checklist.
- `TransactionData` now carries `amountMinor`, direction, status, reversal, unstructured message, counterparty, payment type, end-to-end id, `PaymentSymbols` and the original payload in `raw`.
- `AccountService::getBalances()` returns every balance for an account (booked CLBD and available CLAV) with `amountMinor`.
- `AccountData::$accountNumber` from the account BBAN (`identification.other`).
- Named connections via `profiles`, `default_profile` and `Moneta::profile()`.
- Sandbox client credentials: the first API call requests an access token and caches it. A `401` refreshes that token once.
- `TwoFactorRequiredException` for HTTP 401 with error code `NARR`.
- `TransactionService::cursor()` and `between()` walk transaction pages lazily.
- `Moneta::token()->introspect()` returns the static token expiration. HTTP 403 means the token is not a Moneta API token.
- `Moneta::fake()` without arguments builds accounts, transactions, balances and token expiration locally and can assert which reads ran. A non-empty URL map still uses `Http::fake()`.

### Changed

- Tests run with PHPUnit (`composer test`).
- The AISP OpenAPI specification lives in `.ai/specs/moneta/`.

### Deprecated

- `TransactionData::$amount` and `BalanceData::$value`. Use `amountMinor`.

### Upgrade Notes

- `TransactionData::$amount` and `BalanceData::$value` stay available and are deprecated in favour of `amountMinor` (integer haléře).
- Config keys `profiles` and `default_profile` (`MONETA_PROFILE`) add named connections. The profile `default` is still the flat configuration.
- Sandbox mode with a client id and secret requests the access token on the first API call. `withClientCredentials()` still works.
- `TwoFactorRequiredException` extends `UnauthorizedException`. An existing `catch (UnauthorizedException)` still handles HTTP 401 `NARR`.
- `Moneta::fake()` without arguments returns `MonetaFake`. `Moneta::fake([...])` still passes the map to `Http::fake()`.

## [1.1.0] - 2026-08-11

### Changed

- OAuth scope is taken from configuration when it is set. An empty scope falls back to `vip_sandbox` only when sandbox mode is on, and an empty scope is omitted from the token request.
- Removed the duplicate `use_sandbox` config key. Sandbox mode is `sandbox`.
- Statement download sends `accountStatementId` only.

## [1.0.0] - 2026-08-11

### Added

- AISP client for own accounts, balances, transactions and statements, including statement download and file download.
- Static bearer token authentication and OAuth2 client credentials with a cached access token.
- Optional context headers for identification number, business name and application name.
- DTOs for accounts, balances, transactions and statements.
- Mapping of HTTP failures to unauthorized, validation, not found, rate limit and network exceptions.
- Test suite on Orchestra Testbench with `Http::fake()`.
