# Changelog

All notable changes to this project will be documented in this file.

The format is based on Keep a Changelog.

## [Unreleased]

## [1.4.0] - 2026-11-14

### Added

- `moneta:accounts` lists accounts and booked/available balances. `--json` prints the same rows. `--profile=` selects a connection.
- `moneta:check` reports the profile, mode, base URL, whether credentials are set, the account count and static-token expiration. A token inside `--warn-days` (default 14) is a warning and still exits 0. Any failed check exits 1.
- `StatementService::json()` reads an official JSON statement (`JSON$EMBD`, or the file behind `dataDownloadURL`) into minor-unit balances. `verify()` checks that the opening balance plus credits minus debits equals the closing balance. `transactionTotals()` sums the statement lines. `listFor()` lists one calendar month.

### Fixed

- `Moneta::profile()`, `withToken()` and `withContext()` on `MonetaFake` share accounts, transactions, balances and recorded calls. `assertProfileUsed()` checks the profile name stored on each call.
- `BalanceData::$signedAmountMinor` carries the sign (`DBIT` is negative) and `isNegative()` reports it. `$amountMinor` stays the absolute value.
- HTTP 429 waits for `Retry-After` (seconds or an HTTP date). Without that header the delay is `retry_sleep * 2^(attempt-1)`, capped by `retry_max_sleep` (default 5000 ms). Only GET requests and the OAuth token endpoint are retried. Payment POSTs are not.
- A transaction amount with more than two decimal places sets `amountInvalid` and `amountMinor` to 0 instead of aborting `cursor()`. The original payload stays in `raw`.
- `TransactionData::hasReference()` is false when `entryReference` is missing. Pending movements and blocks may omit it.

### Upgrade Notes

- `BalanceData::$amountMinor` stays the absolute value. Use `$signedAmountMinor` for the sign (`DBIT` is negative) and `isNegative()`.
- A transaction amount with more than two decimal places no longer aborts `cursor()`. `amountMinor` is `0`, `amountInvalid` is `true`, and `raw` still holds the payload. Skip and log those rows.
- `hasReference()` is false when `entryReference` is missing. Pending movements and blocks cannot be deduplicated by that id.
- HTTP 429 waits for `Retry-After` (seconds or an HTTP date). Without it the delay is `retry_sleep * 2^(attempt-1)`, capped by `retry_max_sleep` (default 5000 ms). Only GET requests and the OAuth token endpoint are retried. Payment POSTs are not.
- `moneta:accounts` and `moneta:check` are new. They are registered only in the console.
- `Moneta::profile()`, `withToken()` and `withContext()` on `MonetaFake` share one state, so recorded calls and seeded data are visible on every clone.
- `StatementService::json()`, `verify()`, `transactionTotals()` and `listFor()` are new. Existing statement list and file download calls are unchanged.

## [1.3.0] - 2026-11-14

### Added

- `MinorAmount::toApi()` formats integer haléře as a two-decimal string. The payment JSON sends that string as a number, without a float conversion.
- `PaymentOrderData` and `Moneta::payments()` prepare a batch (`POST /api/v4/vip/pisp/my/payments/batch`) or a one-by-one batch for signature in Internet Banking. The package does not execute a payment.
- `batchStatus()` and `oneByOneStatus()` map `ACTC`, `ACSP` and `RJCT`.
- `MonetaFake` records batches. `withBatchStatus()`, `assertBatchCreated()` and `assertNoBatchCreated()` cover them.

### Upgrade Notes

- Payment batches are new. Account, balance and transaction behaviour is unchanged.
- A batch waits for the client's signature. Priority is always `NORM`. `HIGH` and `INST` are not sent.
- A batch accepts at most 200 payments. An invalid account, a symbol longer than 10 digits, or a larger batch throws `InvalidPaymentOrderException` before any HTTP call.

## [1.2.0] - 2026-11-14

> Tag `1.2.0` ukazuje na kód 1.1.0, použij `1.2.2` nebo novější.

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
