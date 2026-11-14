# 007 – (později) PISP: dávkové platby k podpisu

Stav: čeká na rozhodnutí Petra, jestli platby z aplikace chce. Nezačínej bez potvrzení.

## Cíl
Aplikace připraví platby (např. dodavatelům) jako dávku, kterou Petr podepíše v Internet Bance, a sleduje její stav.

## Kontext
- `.ai/specs/moneta/VipPISPPaymentsBatch_v4.json`: `POST /api/v4/vip/pisp/my/payments/batch` (max. 200 plateb, jen `NORM`) → `batchId`; `GET …/batch/{batchId}/status` → `ACTC` | `ACSP` | `RJCT`. Varianta `…/batch/onebyone` vrací `transactionIdentification` pro každou platbu a stav jednotlivě.
- Platba: `debtorAccount`, `creditorAccount` (IBAN nebo BBAN `[prefix-]číslo/kód`), `amount.instructedAmount{value,currency}`, `remittanceInformation.structured.creditorReferenceInformation.reference` (`VS:…,KS:…,SS:…`, max. 10 číslic), `unstructured`, `requestedExecutionDate`, `paymentIdentification.instructionIdentification` (max. 35 znaků).
- Sandbox aplikace v portálu musí mít přiřazené API `VipPISPPaymentsBatch_v4` s plánem Sandbox.

## Kroky (návrh)
1. DTO `PaymentOrderData` (částka v haléřích → API přes `MinorAmount::toApi()`), validace BBAN/IBAN, symbolů a délek před odesláním (`InvalidPaymentException`).
2. `PaymentService::createBatch(array $orders): BatchResultData`, `batchStatus(string $batchId): BatchStatus` enum, `createOneByOne()`, `oneByOneStatus()`.
3. Fake: `MonetaFake::assertBatchCreated(?callable)`.

## Hotovo když
- Testy proti příkladům ze specifikace, validace odmítne neplatný účet a dlouhé symboly.
