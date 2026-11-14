# 010 – výpis v JSON a kontrolní součty

## Cíl
Aplikace může jednou za měsíc porovnat stažené pohyby s oficiálním výpisem banky (počáteční a konečný zůstatek, součty příjmů a výdajů) a odhalit, že synchronizace něco vynechala.

## Kontext
- `.ai/specs/moneta/VipAISPPayments_v4.json`: `GET /api/v4/vip/aisp/my/accounts/{id}/statements?from&to` → `accountStatements[]` (`accountStatementId`, `dateFrom`, `dateTo`, `period`, `year`, `month`, `sequenceNumber`, `formats[]`).
- `GET /api/v4/vip/aisp/my/accounts/{id}/statement/download?accountStatementId&format=JSON&feature=JSON$EMBD|JSON$STND` → `AccountStatementResponse`: `dataDownloadURL` nebo vložené `exportStatement` (`accountNumber`, `accountPrefix`, `bankCode`, `currency`, `begBalance`, `finBalance`, `creditSum`, `debitSum`, `period`, `sequenceNumber`, `generateDate`, `rejTurnCount`) a `transactions[]` (`AccountStatementTransaction`).
- `StatementService::download()` dnes vrací surové pole. Výpis existuje jen u elektronicky zasílaných výpisů. Sandbox vrací jen XML – JSON testuj přes fake.

## Kroky
1. DTO `StatementSummaryData` (z `exportStatement`): `accountNumber` (s prefixem), `bankCode`, `currency`, `beginBalanceMinor`, `endBalanceMinor`, `creditSumMinor`, `debitSumMinor` (vše přes `MinorAmount::fromApi()`), `period`, `sequenceNumber`, `?generatedAt`.
2. DTO `StatementTransactionData` z `AccountStatementTransaction` (`entryReference`, `amountMinor`, směr, `bookingDate`, `valueDate`, symboly z `references`/`additionalTransactionInformation`, protistrana) – jen co je ve schématu, nevymýšlej pole.
3. `StatementService::json(string $accountId, string $statementId): StatementJsonData` (`summary`, `transactions`, `raw`). Když API vrátí jen `dataDownloadURL`, stáhni soubor přes `downloadFile()` a zparsuj JSON. Neplatný JSON → `ApiException` s jasnou zprávou.
4. `StatementJsonData::verify(): bool` – `begin + creditSum − debitSum === end` (v haléřích). A `transactionTotals(): array{credit:int, debit:int}` z položek výpisu.
5. `StatementService::listFor(string $accountId, int $year, int $month)` – výpisy za měsíc (`from`/`to` = první a poslední den).
6. `MonetaFake`: `withStatement(string $accountId, array $summary, array $transactions = [])`.
7. README sekce Statements (doporučený postup kontroly v aplikaci), `CHANGELOG.md` Unreleased.

## Nedělat
- Neparsovat PDF, XML ani ABO (zatím).
- Žádné ukládání do DB.

## Hotovo když (`composer test`)
- Vložený JSON výpis → správné souhrny v haléřích, `verify() === true`.
- Výpis s nesedícím konečným zůstatkem → `verify() === false`.
- Varianta s `dataDownloadURL` stáhne soubor a zparsuje ho.
- `listFor(2026, 2)` pošle `from=2026-02-01`, `to=2026-02-28`.

## Po dokončení
- Stav do `.ai/tasks/README.md`.
