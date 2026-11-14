# Release Notes: 1.2.0

## Summary

Minor release for reading Moneta accounts, balances and transactions in a form an application can store and match, including named connections and a local test fake.

## Highlights

- Transaction and balance amounts are available as `amountMinor` (integer haléře), with direction, status, reversal, counterparty, payment symbols and the original payload.
- `cursor()` and `between()` walk transaction pages without loading the whole history first.
- `profiles` and `Moneta::profile()` select another token or company. `default` stays the flat configuration.
- Sandbox client credentials request and cache an access token automatically. One `401` refreshes it.
- `Moneta::token()->introspect()` reports when a static API token expires.
- `Moneta::fake()` without arguments builds responses locally and asserts which reads ran.

## Upgrade Notes

- `TransactionData::$amount` and `BalanceData::$value` stay available and are deprecated in favour of `amountMinor`.
- New config keys are `profiles` and `default_profile` (`MONETA_PROFILE`). Existing flat keys keep their meaning.
- Sandbox mode with only a client id and secret now requests the access token on the first call. `withClientCredentials()` still works.
- `TwoFactorRequiredException` extends `UnauthorizedException`, so an existing `catch (UnauthorizedException)` still handles HTTP 401 with error `NARR`.
- `Moneta::fake()` without arguments returns `MonetaFake`. A non-empty URL map is still passed to `Http::fake()`.

## Validation

- `composer validate --strict`
- `composer test`
