# 008 – opravy po kontrole verze 1.2.2

## Cíl
Opravit nálezy z kontroly kódu 1.2.2, aby na balíčku mohla stavět aplikace (úkol 008 v it-elektro).

## Kontext a nálezy
1. **Fake a `profile()`** – `MonetaManager::profile()` vrací `clone $this`. U `MonetaFake` se tím zkopírují pole `accounts`, `transactions`, `balances` a `calls`. Volání přes `Moneta::profile('default')->transactions()->…` se zapíše jen do klonu, takže `assertTransactionsFetched()` na původním fake selže, a data přidaná do fake po vytvoření klonu klon nevidí. Aplikace volá `Moneta::profile($account->moneta_profile)`.
2. **Znaménko zůstatku** – `BalanceData::$amountMinor` je vždy kladné i při `creditDebitIndicator = DBIT` (záporný zůstatek). Chybí hodnota se znaménkem.
3. **429 a opakování** – `retry` čeká pevně `retry_sleep` ms (100) a ignoruje hlavičku `Retry-After`. Při rate limitu tři rychlé pokusy nic nevyřeší.
4. **Částka s víc než 2 desetinnými místy** – `MinorAmount::fromApi()` vyhodí výjimku uvnitř `TransactionData::fromArray()`, a tím shodí celý `cursor()` (jedna divná transakce zastaví synchronizaci).
5. **Transakce bez `entryReference`** – u blokací a čekajících pohybů (`PDNG`, `BLOCKED_*`) může `entryReference` chybět, DTO pak má prázdný string. Aplikace podle něj deduplikuje.

## Kroky
1. Fake: stav (`accounts`, `transactions`, `balances`, `tokenExpiration`, `failure*`, `entrySequence`, `calls`) přesuň do jednoho sdíleného objektu (např. `final class MonetaFakeState`), který klony sdílejí referencí. `profile()`, `withContext()`, `withToken()` na fake pak vidí stejná data a zapisují do stejných `calls`. Volitelně zaznamenej v `calls` i název profilu a přidej `assertProfileUsed(string $name)`.
2. `BalanceData`: přidej `int $signedAmountMinor` (záporné při `DBIT`) a `isNegative(): bool`. `amountMinor` nech beze změny (kompatibilita), v PHPDoc uveď, že je to absolutní hodnota.
3. Opakování:
   - čekání na 429 podle `Retry-After` (sekundy nebo HTTP datum), jinak exponenciálně `retry_sleep * 2^(pokus-1)`, strop config `retry_max_sleep` (výchozí 5000 ms)
   - opakovat jen idempotentní požadavky (GET a token endpoint); POST (platby) nikdy – pokud to už udělal úkol 007, jen ověř testem
4. `TransactionData::fromArray()`: když převod částky selže, nevyhazuj. Nastav `amountMinor = 0` a nový příznak `bool $amountInvalid = true` (a `raw` zůstane). `cursor()` takovou transakci normálně vrátí. V README napiš, že aplikace má takové transakce přeskočit a zalogovat.
5. `TransactionData`: přidej `hasReference(): bool` (`entryReference !== ''`). V README sekci Transactions upozorni, že čekající pohyby a blokace nemusí mít `entryReference` a nelze je podle něj deduplikovat.
6. `CHANGELOG.md` Unreleased.

## Nedělat
- Neměnit význam stávajících vlastností.
- Netagovat, necommitovat.

## Hotovo když (`composer test`)
- `Moneta::fake()->incomingPayment('acc-1', 100)`, pak `Moneta::profile('default')->transactions()->all('acc-1')` vrátí platbu a `Moneta::assertTransactionsFetched('acc-1')` (na původním fake) projde.
- Zůstatek `DBIT 150.00` → `signedAmountMinor = -15000`, `amountMinor = 15000`.
- 429 s `Retry-After: 2` → další pokus až po ~2 s (testuj přes `Sleep::fake()` / `Http::fake` sekvenci); POST se neopakuje.
- Transakce s `amount.value = 1.234` → `amountInvalid = true`, `cursor()` vrátí ostatní transakce.
- Transakce bez `entryReference` → `hasReference() === false`.

## Po dokončení
- Stav do `.ai/tasks/README.md`.
