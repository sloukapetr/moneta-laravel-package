# 009 – Artisan příkazy `moneta:accounts` a `moneta:check`

## Cíl
Při nasazení a ladění rychle ověřit, že připojení k Monetě funguje, bez psaní kódu v aplikaci.

## Kontext
- Balíček zatím nemá žádné příkazy. `MonetaServiceProvider` jen merguje a publikuje config.
- Profily: `Moneta::profile($name)`, výchozí `default`. Introspect funguje jen se statickým tokenem (v sandboxu vrací 403).

## Kroky
1. `moneta:accounts {--profile=}` – tabulka účtů: API id, číslo účtu (`accountNumber`), IBAN, měna, název, disponibilní a účetní zůstatek (`getBalances()`, `signedAmountMinor` formátovaně se 2 desetinnými místy). Volba `--json` vypíše totéž jako JSON (pro skripty).
2. `moneta:check {--profile=}` – diagnostika, každý řádek OK / CHYBA:
   - profil a režim (sandbox / produkce), base URL
   - autentizace: statický token nastaven / client credentials nastaveny (hodnoty **nikdy nevypisuj**, jen „nastaveno“)
   - volání `accounts()->all()` – počet účtů, doba odpovědi
   - u statického tokenu `token()->introspect()` – datum expirace a počet dní; varování při ≤ 14 dnech (volba `--warn-days=`)
   - návratový kód: 0 vše OK, 1 chyba (pro monitoring a CI na serveru)
3. Registrace v `MonetaServiceProvider::boot()` jen `runningInConsole()`.
4. README sekce Commands, `CHANGELOG.md` Unreleased.

## Nedělat
- Nevypisovat token, client secret ani access token, ani částečně.
- Žádné zápisy do databáze.

## Hotovo když (`composer test`)
- Testy přes `Moneta::fake()`: `moneta:accounts` vypíše účet ze fake, `--json` vrací validní JSON.
- `moneta:check` s fake expirací za 5 dní vrátí varování a kód 0; s `failWith(401)` vrátí kód 1.
- Test, že výstup neobsahuje hodnotu `api_token` ani `client_secret` z configu.

## Po dokončení
- Stav do `.ai/tasks/README.md`.
