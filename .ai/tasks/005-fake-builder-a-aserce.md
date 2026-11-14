# 005 – `Moneta::fake()` s builderem a asercemi

## Cíl
Aplikace testuje práci s Monetou bez znalosti URL a tvaru JSON odpovědí.

## Kontext
- `Moneta::fake(array $responses)` dnes jen volá `Http::fake($responses)`.
- Vzor: `Sloukapetr\ComgateLaravelPackage\Fakes\ComgateFake` (dědí ze služby a přepisuje jen transport).

## Kroky
1. `src/Fakes/MonetaFake.php` dědí z `MonetaManager` a přepisuje jen `rawRequest()` (vrací odpovědi sestavené z builderu, nic neposílá).
2. `Moneta::fake()`:
   - s neprázdným polem → dosavadní chování (`Http::fake`), kvůli kompatibilitě
   - bez argumentu → swap instance v kontejneru za `MonetaFake` a vrátí ji
3. Builder na `MonetaFake`:
   - `withAccount(string $id, array $overrides = [])`
   - `withTransaction(string $accountId, array $overrides = [])` a `incomingPayment(string $accountId, int $amountMinor, ?string $vs = null, array $overrides = [])` (vytvoří BOOK/CRDT transakci s unikátním `entryReference`)
   - `withBalance(string $accountId, int $bookedMinor, ?int $availableMinor = null)`
   - `withTokenExpiration(CarbonInterface $at)`
   - `failWith(int $status, string $errorCode = 'ERROR')` pro další volání
   Výstup builderu musí mít stejný tvar jako schémata ve specifikaci (aby šel přes stávající `fromArray()`), včetně stránkování transakcí podle `size`.
4. Aserce: `assertAccountsFetched()`, `assertTransactionsFetched(string $accountId, ?callable $filters = null)`, `assertNothingFetched()`.
5. README sekce Testing, `CHANGELOG.md` Unreleased.

## Nedělat
- Neměnit chování `Moneta::fake([...])` s polem.

## Hotovo když (`composer test`)
- `Moneta::fake()->incomingPayment('acc-1', 150000, '2026001')` → `Moneta::transactions()->all('acc-1')` vrátí transakci s `amountMinor = 150000`, `symbols->vs = '2026001'`, `isSettledIncomingPayment()`.
- 250 transakcí přes `cursor()` = 3 stránky.
- `failWith(401, 'NARR')` → `TwoFactorRequiredException`.
- Aserce fungují a selžou se srozumitelnou zprávou.

## Po dokončení
- Stav do `.ai/tasks/README.md`.
