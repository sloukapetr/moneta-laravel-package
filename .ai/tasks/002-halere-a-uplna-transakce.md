# 002 – částky v haléřích a úplná transakce

## Cíl
Aplikace dostane z balíčku všechno, co potřebuje k uložení a spárování platby: částku v haléřích, směr, stav, storno, symboly, zprávu, protiúčet, typ platby a původní data.

## Kontext
- `TransactionData::$amount` a `BalanceData::$value` jsou `float`. Pro peníze nepoužitelné (zaokrouhlení).
- `TransactionData` nemá `reversalIndicator`, protistranu (`relatedParties`), nestrukturovanou zprávu (`remittanceInformation.unstructured`), `references.clearingSystemReference`, `references.endToEndIdentification` ani původní pole.
- Tvar transakce: schéma `AispTransaction` ve `.ai/specs/moneta/VipAISPPayments_v4.json`. Protistrana: `entryDetails.transactionDetails.relatedParties.{debtor,creditor}.name` a `{debtorAccount,creditorAccount}.identification.{iban,other.identification}`. U příchozí platby (CRDT) je protistrana `debtor`, u odchozí (DBIT) `creditor`.
- Symboly mohou být v `reference` oddělené mezerou i čárkou (`VS:123 KS:0308` nebo `VS:123,KS:0308`). Současný regex používá `\b`, ověř oba tvary testem.

## Kroky
1. `src/Support/MinorAmount.php` (final): `fromApi(int|float|string $value): int` – převod přes string (`number_format($value, 2, '.', '')` u float, pak odstranit tečku), podpora záporných hodnot, chyba při víc než 2 desetinných místech. Testy: `0.1`, `1234.5`, `1234.56`, `-10`, `"99.99"`, `100`.
2. `src/Support/PaymentSymbols.php` (final, readonly): `vs`, `ks`, `ss`, `static fromReference(?string)`. Přesuň do něj logiku z `TransactionData::extractSymbols()` (tu nech jako deprecated volání).
3. Enumy `src/Enums/`: `TransactionStatus` (`Booked = 'BOOK'`, `Pending = 'PDNG'`), `CreditDebit` (`Credit = 'CRDT'`, `Debit = 'DBIT'`), `BalanceType` (`Booked = 'CLBD'`, `Available = 'CLAV'`). Neznámá hodnota z API nesmí shodit mapování – v DTO pak `null` u nové vlastnosti.
4. `TransactionData` – **přidat** (stávající vlastnosti zachovat, `amount` označit `@deprecated` ve prospěch `amountMinor`):
   - `int $amountMinor`, `?TransactionStatus $statusEnum`, `?CreditDebit $direction`, `bool $isReversal`
   - `?string $message` (unstructured), `?string $counterpartyName`, `?string $counterpartyAccount` (BBAN z `other.identification`, jinak IBAN), `?string $counterpartyIban`
   - `?string $paymentType` (`clearingSystemReference`), `?string $endToEndIdentification`
   - `PaymentSymbols $symbols`, `array $raw`
   - pomocné metody `isIncoming()`, `isBooked()`, `isSettledIncomingPayment()` (= booked, credit, ne storno)
   Nové vlastnosti přidávej na konec konstruktoru s výchozí hodnotou, aby zůstala kompatibilita pozičních argumentů.
5. `BalanceData`: přidat `int $amountMinor` a `?BalanceType $balanceType` enum (stávající `value`, `type`, `balanceType` string zachovat, `value` deprecated). `AccountService::getBalance()` vrací dál první zůstatek; přidej `getBalances(string $id): array<BalanceData>` se všemi (CLBD i CLAV).
6. `AccountData`: přidat `?string $accountNumber` (`identification.other`, BBAN).
7. README sekce Transactions: příklad práce s `amountMinor`, `symbols->vs`, `isSettledIncomingPayment()`. `CHANGELOG.md` Unreleased.

## Nedělat
- Nemazat ani nepřejmenovávat stávající vlastnosti a metody.
- Žádné výpočty s `float`.

## Hotovo když (`composer test`)
- `MinorAmount` testy výše.
- Příchozí BOOK platba s `VS:2026001 KS:0308 SS:12` → `amountMinor`, `symbols`, `direction = Credit`, protistrana z `debtor`.
- Odchozí platba → protistrana z `creditor`.
- `reversalIndicator: true` → `isSettledIncomingPayment() === false`.
- PDNG → `isBooked() === false`.
- Reference s čárkami i mezerami dává stejné symboly.
- `getBalances()` vrátí CLBD i CLAV s `amountMinor`.
- Stávající testy beze změny procházejí.

## Po dokončení
- Stav do `.ai/tasks/README.md`.
