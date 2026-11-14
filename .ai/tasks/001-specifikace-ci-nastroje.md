# 001 – specifikace, CI, CHANGELOG a nástroje jako u comgate

## Cíl
Repo má specifikace API na jednom místě, CI pro PHP 8.2–8.5 a stejné nástroje a soubory pro vydání jako `comgate-laravel-package`.

## Kontext
- V kořeni leží `VipAISPPayments_v4.json` (specifikace AISP). Ostatní specifikace (PISP batch, standing orders, token introspect) v repu nejsou.
- `composer.json` má v `require-dev` Pest, ale testy jsou PHPUnit třídy (`test_…`). Comgate balíček používá PHPUnit (`"test": "vendor/bin/phpunit"`) a má `phpunit.xml`.
- Repo nemá `.github/workflows/ci.yml`, `CHANGELOG.md` ani `RELEASE_CHECKLIST.md`. Vzor je v `C:\Users\slouka\comgate-laravel-package`.
- Aplikace it-elektro běží na PHP 8.5 a Laravelu 13.

## Kroky
1. Přesuň `VipAISPPayments_v4.json` do `.ai/specs/moneta/VipAISPPayments_v4.json`. Pokud Petr dodal další specifikace (viz Ruční kroky), ulož je vedle (`VipPISPPaymentsBatch_v4.json`, `VipPISPStandingOrders_v4.json`, `VipTokenIntrospect_v1.json`).
2. Testy na PHPUnit jako comgate: odeber Pest z `require-dev` a `allow-plugins`, smaž `tests/Pest.php`, přidej `phpunit.xml` (vzor z comgate), `"test": "vendor/bin/phpunit"`. Ověř, že všechny stávající testy běží.
3. `.github/workflows/ci.yml` podle comgate, matice: 8.2/L11, 8.2/L12, 8.3/L13, 8.4/L13, 8.5/L13 (testbench ^9/^10/^11). Když 8.5 v `setup-php` není, `continue-on-error` jen pro tento řádek.
4. `composer.json`: doplň `keywords`, `homepage`, `support` (GitHub `sloukapetr/moneta-laravel-package`), `minimum-stability: stable`, `prefer-stable: true`. `composer validate --strict` musí projít.
5. `.gitattributes`: odeber řádek pro `VipAISPPayments_v4.json`, přidej `/.ai export-ignore`, `/.cursor export-ignore`, `/AGENTS.md export-ignore`, `/RELEASE_* export-ignore`, `/phpunit.xml export-ignore`.
6. `CHANGELOG.md` (Keep a Changelog) s historií `1.0.0`, `1.1.0` podle git tagů a sekcí Unreleased. `RELEASE_CHECKLIST.md` podle comgate.

## Nedělat
- Neměnit `src/` ani chování.
- Nepřejmenovávat balíček ani namespace (je vydaný).

## Hotovo když
- `composer validate --strict` a `composer test` projdou lokálně.
- Specifikace jsou jen v `.ai/specs/moneta/`.

## Ruční kroky pro Petra
- Stáhni zbylé specifikace z portálu (po přihlášení) a ulož je do `.ai/specs/moneta/` pod názvem z odpovědi (`name`), obsah = pole `content`:
  - https://apiportal.moneta.cz/api/portal/api-management/1.0/apis/d8ea4013-bae0-40eb-b23e-e9eccfbe8be4/assets/swagger
  - https://apiportal.moneta.cz/api/portal/api-management/1.0/apis/0ed61e6c-8be7-4fdf-9c4e-d918c4835c94/assets/swagger
  - https://apiportal.moneta.cz/api/portal/api-management/1.0/apis/f0ac3a5f-b374-4e76-bb77-7768d26be742/assets/swagger

## Po dokončení
- Stav do `.ai/tasks/README.md`.
