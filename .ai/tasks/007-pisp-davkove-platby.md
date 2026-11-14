# 007 – PISP: dávkové platby k podpisu (vydání 1.3.0)

## Cíl
Aplikace připraví platby (např. dodavatelům) jako dávku, kterou Petr podepíše v Internet Bance, a sleduje její stav. Balíček nic neodesílá bez podpisu (API to ani neumí). Vyjde jako `1.3.0`.

## Krok 0 – ověř git a tagy (nic neopravuj, jen nahlas)
Spusť a výsledek dej na začátek shrnutí:
```bash
git status --short
git log --oneline -5
git tag --sort=-creatordate | head -5
for t in 1.2.0 1.2.1 1.2.2; do echo "$t -> $(git rev-list -n 1 $t) $(git log -1 --format=%s $t)"; done
git ls-remote --tags origin
git rev-parse HEAD origin/main
```
Očekávaný stav (podle kontroly 14. 11.): `1.2.0` ukazuje na commit „Release 1.1.0“ (**špatně**), `1.2.1` a `1.2.2` na „Expand AISP features and prepare v1.2.0“ (správně). Napiš:
- jestli je pracovní strom čistý a `HEAD == origin/main`,
- které tagy jsou na GitHubu (`ls-remote`) a na jaký commit ukazují,
- jestli se něco liší od očekávaného stavu.
Tagy **nemaž, nepřesouvej ani nevytvářej**. To udělá Petr. Když pracovní strom není čistý, zastav se a napiš to.

## Kontext
- `.ai/specs/moneta/VipPISPPaymentsBatch_v4.json`: `POST /api/v4/vip/pisp/my/payments/batch` (max. 200 plateb, jen priorita `NORM`) → `{ batchId }`; `GET /api/v4/vip/pisp/my/payments/batch/{batchId}/status` → `{ instructionStatus: ACTC | ACSP | RJCT }`. Varianta `POST …/batch/onebyone` → `{ totalCount, items: [{ instructionIdentification, transactionIdentification }] }`, stav `GET …/batch/onebyone/{transactionId}/status`.
- **Když soubor specifikace v `.ai/specs/moneta/` není, zastav se a napiš to** (Petr ho stáhne z portálu). Nevymýšlej tvar API.
- Platba (`PaymentInitRequestData`): povinné `amount.instructedAmount{value,currency}`, `debtorAccount`, `creditorAccount` (`identification.iban` nebo `identification.other.identification` = BBAN `[prefix-]číslo/kód`: prefix 1–6 číslic, číslo 2–10 číslic, kód banky 4 číslice). Volitelné: `remittanceInformation.structured.creditorReferenceInformation.reference` (`VS:123,KS:1234,SS:123`, každý max. 10 číslic), `remittanceInformation.unstructured`, `requestedExecutionDate` (Y-m-d), `paymentIdentification.instructionIdentification` (max. 35 znaků), `creditor.name`, `chargeBearer` (`SHA|OUR|BEN`), `paymentTypeInformation.instructionPriority` (u dávky jen `NORM`).
- Stav: `ACTC` = čeká na podpis klienta, `ACSP` = podepsáno, `RJCT` = zamítnuto.
- `MinorAmount::fromApi()` už existuje. Opačný převod zatím ne.
- Commit 1.2.x zmiňuje „remove the unsupported PISP service“. Zkontroluj, že po něm nezůstal mrtvý kód, a nový kód stav znovu podle tohoto zadání.

## Kroky
1. `MinorAmount::toApi(int $minor): string` – `150050` → `"1500.50"`, `-10` → `"-0.10"`, `100` → `"1.00"`. V JSON posílej jako číslo s 2 desetinnými místy bez převodu přes float (např. `json_encode` s předpočítaným stringem přes `JSON_PRESERVE_ZERO_FRACTION` nebo vlastní serializaci – zvol bezpečnou variantu a otestuj).
2. `src/Data/PaymentOrderData.php` (final, readonly): `amountMinor`, `currency`, `debtorAccount`, `creditorAccount`, `?vs`, `?ks`, `?ss`, `?message`, `?creditorName`, `?requestedExecutionDate`, `?instructionId`. `toArray()` do tvaru API. Validace v konstruktoru → `InvalidPaymentOrderException` (BBAN/IBAN, symboly ≤ 10 číslic, `instructionId` ≤ 35, částka > 0).
3. Enum `BatchStatus` (`AwaitingSignature = 'ACTC'`, `Signed = 'ACSP'`, `Rejected = 'RJCT'`).
4. `src/Services/PaymentService.php` + `Moneta::payments()`:
   - `createBatch(array $orders): string` (batchId), max. 200 položek jinak výjimka
   - `batchStatus(string $batchId): BatchStatus`
   - `createOneByOne(array $orders): array` (`instructionIdentification` → `transactionIdentification`)
   - `oneByOneStatus(string $transactionId): BatchStatus`
5. `MonetaFake`: zaznamenává dávky, `withBatchStatus(string $batchId, BatchStatus $status)`, aserce `assertBatchCreated(?callable $callback = null)`, `assertNoBatchCreated()`.
6. Facade `@method`, README sekce Payments (s upozorněním, že platby se jen připravují a podepisují v Internet Bance), `CHANGELOG.md`.
7. Vydání: `CHANGELOG.md` `[1.3.0] - <datum>`, `RELEASE_NOTES_v1.3.0.md`, projít `RELEASE_CHECKLIST.md`. Do CHANGELOG doplň u verzí 1.2.x poznámku: *tag `1.2.0` ukazuje na kód 1.1.0, použij `1.2.2` nebo novější* (nebo jak rozhodne Petr podle kroku 0).

## Nedělat
- Žádné odesílání plateb bez podpisu, žádné „okamžité“ platby (`HIGH`, `INST`).
- Netagovat, necommitovat, nemazat tagy.
- Neměnit chování AISP.

## Hotovo když (`composer validate --strict`, `composer test`)
- `toApi()` testy výše a zpětný test `fromApi(toApi($x)) === $x`.
- Neplatný účet, dlouhý symbol nebo 201 plateb → výjimka před HTTP voláním.
- `createBatch()` pošle tvar podle specifikace a vrátí `batchId`; `batchStatus()` mapuje všechny tři stavy.
- `Moneta::fake()->…` + `assertBatchCreated()` funguje.

## Ruční kroky pro Petra
- Před úkolem: stáhnout `VipPISPPaymentsBatch_v4.json` do `.ai/specs/moneta/` a v portálu přidat aplikaci „Laravel Moneta Integration“ API `VipPISPPaymentsBatch_v4` s plánem Sandbox.
- Tagy podle kroku 0: doporučení – smazat chybný `1.2.0` (`git tag -d 1.2.0`, `git push origin :refs/tags/1.2.0`) a na Packagistu udělat Update. `1.2.1` a `1.2.2` nechat.
- Po úkolu: commit, **nejdřív commit, pak** `git tag 1.3.0`, ověřit `git log -1 --format=%s 1.3.0`, `git push && git push --tags`.

## Po dokončení
- Stav do `.ai/tasks/README.md`.
