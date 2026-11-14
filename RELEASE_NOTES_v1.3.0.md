# Release Notes: 1.3.0

## Summary

Minor release that prepares payment batches for signature in Internet Banking. The package still does not execute a payment.

## Highlights

- `Moneta::payments()->createBatch()` sends up to 200 payments to `POST /api/v4/vip/pisp/my/payments/batch` and returns `batchId`.
- `createOneByOne()` returns `instructionIdentification` mapped to `transactionIdentification`.
- `batchStatus()` and `oneByOneStatus()` map `ACTC` (awaiting signature), `ACSP` (signed) and `RJCT` (rejected).
- Amounts stay integer haléře. `MinorAmount::toApi()` writes a two-decimal number into the JSON body without a float conversion.
- `MonetaFake` records the batch and can assert it with `assertBatchCreated()`.

## Upgrade Notes

- Account, balance and transaction calls are unchanged.
- The client signs the prepared batch in Internet Banking. The package never sends priority `HIGH` or `INST`.
- Tag `1.2.0` points at the 1.1.0 code. Install `1.2.2` or newer for the 1.2 line, then `1.3.0` for payments.

## Validation

- `composer validate --strict`
- `composer test`
- `composer outdated --direct`
