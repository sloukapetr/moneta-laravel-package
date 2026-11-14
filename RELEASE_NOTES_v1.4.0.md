# Release Notes: 1.4.0

## Summary

Minor release for the fixes after 1.2.2, console checks, and the official JSON statement with control totals. Account and transaction reads stay compatible.

## Highlights

- `BalanceData::$signedAmountMinor` is negative for `DBIT`. `$amountMinor` stays the absolute value.
- A transaction amount with more than two decimal places sets `amountInvalid` instead of stopping `cursor()`. `hasReference()` is false when `entryReference` is missing.
- HTTP 429 follows `Retry-After`, otherwise exponential backoff capped by `retry_max_sleep`. Payment POSTs are not retried.
- `moneta:accounts` and `moneta:check` verify a connection without application code. A token warning still exits 0. Any failed check exits 1.
- `MonetaFake` clones from `profile()`, `withToken()` and `withContext()` share seeded data and recorded calls.
- `StatementService::json()` reads an official JSON statement. `verify()` checks opening balance + credits − debits = closing balance. `listFor()` lists one calendar month.

## Upgrade Notes

- `$amountMinor` on a balance does not change meaning. Read `$signedAmountMinor` when the sign matters.
- Catching `cursor()` no longer skips the rest of the page because of one bad amount. Check `amountInvalid` and log that transaction.
- Do not deduplicate pending or blocked movements by `entryReference` when `hasReference()` is false.
- Clients that relied on a POST being retried after HTTP 429 or a connection error now get a single attempt. GET and the token endpoint still retry.
- New config key: `retry_max_sleep` (default 5000 ms).
- Install 1.4.0 on top of 1.3.0. Tag `1.2.0` still points at the 1.1.0 code; use `1.2.2` or newer for that line.

## Validation

- `composer validate --strict`
- `composer test`
- `composer outdated --direct`
