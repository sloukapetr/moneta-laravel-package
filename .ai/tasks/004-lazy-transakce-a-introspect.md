# 004 – lazy procházení transakcí a introspect tokenu

## Cíl
Aplikace může stahovat transakce za období bez načtení všeho do paměti a hlídat expiraci produkčního tokenu.

## Kontext
- `TransactionService::all()` načte všechny stránky do pole.
- Filtry `fromDate` / `toDate` filtrují podle `valueDate` (specifikace). Historie max. 2 roky.
- Introspect: `GET /api/v1/vip/token/introspect` → `{ "expiration": "2026-03-06T11:38:09+01:00" }`, `403` když token není Moneta API token (`VipTokenIntrospect_v1.json`).

## Kroky
1. `TransactionService::cursor(string $accountId, array $filters = []): LazyCollection<int, TransactionData>` – stránkuje po 100 přes `nextPage` (stejná ochrana proti zacyklení jako `all()`). `all()` přepiš tak, aby používalo `cursor()->all()`.
2. `TransactionService::between(string $accountId, CarbonInterface|string $from, CarbonInterface|string|null $to = null): LazyCollection` – nastaví `fromDate`/`toDate` ve formátu `Y-m-d`, `sort=date`, `order=ASC`.
3. `src/Services/TokenService.php` + `Moneta::token()->introspect(): TokenInfoData` (`?CarbonImmutable $expiresAt`, `daysUntilExpiry(): ?int`). `403` → `ApiException` s jasnou zprávou.
4. Facade `@method`, README, `CHANGELOG.md` Unreleased.

## Nedělat
- Neměnit návratový typ `all()` (pole).

## Hotovo když (`composer test`)
- `cursor()` projde 3 stránky a vrátí všechny transakce; HTTP volání proběhnou až při iteraci.
- `between()` posílá správné parametry.
- `introspect()` vrátí datum expirace; `403` → výjimka.

## Po dokončení
- Stav do `.ai/tasks/README.md`.
