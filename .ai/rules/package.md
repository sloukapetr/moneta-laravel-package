# Pravidla pro vývoj balíčku moneta-laravel-package

Balíček: `sloukapetr/moneta-laravel-package`, namespace `SloukaPetr\Moneta`. Univerzální Laravel balíček pro firemní Moneta API (VIP API MONETA Money Bank): čtení vlastních účtů, zůstatků, transakcí a výpisů, později příprava plateb k podpisu. Podpora PHP `^8.2`, Laravel 11, 12 a 13. Vydané verze: `1.0.0`, `1.1.0` (dodržuj SemVer).

Dokumentace: OpenAPI specifikace v `.ai/specs/moneta/*.json` (stažené z https://apiportal.moneta.cz). Jiný zdroj pravdy o API není. Co ve specifikaci není, nevymýšlej a napiš jako otázku.

## Moneta API v kostce
- Server `https://api.moneta.cz`. Všechny endpointy `Authorization: Bearer {token}`.
- Produkce: statický token, který majitel účtu vytvoří v Internet Bance. Má expiraci (`GET /api/v1/vip/token/introspect` → `expiration`).
- Sandbox: OAuth2 client credentials `POST /auth/oauth/v2/token` (`client_id`, `client_secret`, `grant_type=client_credentials`, `scope=vip_sandbox`). Vrací mock data bez validací.
- Volitelné hlavičky: `identification_number`, `business_name`, `application_name`.
- AISP (čtení): `/api/v4/vip/aisp/my/accounts`, `…/{id}/balance`, `…/{id}/transactions`, `…/{id}/statements`, `…/{id}/statement/download`, `…/statement?file=`.
- Stránkování: `page` od 0, `size` max 100, v odpovědi `nextPage` jen když další stránka existuje.
- Transakce: `entryReference` (ID od banky), `status` BOOK/PDNG, `creditDebitIndicator` CRDT/DBIT, `reversalIndicator`, symboly v `remittanceInformation.structured.creditorReferenceInformation.reference` ve tvaru `VS:… KS:… SS:…`. Historie max. 2 roky; `401` s kódem `NARR` = potřeba dvoufázového ověření.
- PISP: platby se jen **připravují**, podpis vždy v Internet Bance (`ACTC` čeká na podpis, `ACSP` podepsáno, `RJCT` zamítnuto). Dávka max. 200 plateb, jen `NORM`.
- Chyby: `{ errors: [{ error, message, scope, parameters }] }`. Webhooky API nemá.

## Struktura (drž se jí)
- `src/MonetaManager.php` – jediná transportní vrstva. Všechna HTTP volání jdou přes `rawRequest()`. Nevytvářej paralelního klienta.
- `src/Services/` – `AccountService`, `TransactionService`, `StatementService` (a později `TokenService`, `PaymentService`). Jen skládají cesty a mapují DTO.
- `src/Data/` – DTO s `fromArray()`.
- `src/Enums/` – stavy a směry z API (jediné mapování).
- `src/Support/` – pomocníci (`MinorAmount`, `PaymentSymbols`).
- `src/Exceptions/` – vše dědí z `ApiException`.
- `src/Fakes/` – `Moneta::fake()`.
- `tests/` – kontrakt chování (PHPUnit + Orchestra Testbench).

## Veřejné API a kompatibilita
- Veřejné API = facade `Moneta`, veřejné metody `MonetaManager` a služeb, DTO, enumy, výjimky, config klíče.
- Změna chování nebo signatury = major verze, nebo nová volba s výchozím dosavadním chováním a záznam v Upgrade Notes. Stávající vlastnosti DTO (např. `float $amount`) se nemažou, jen se označí `@deprecated` a přibude nová.
- Každá nová veřejná metoda: `@method` na facade, odkaz na endpoint ze specifikace, test, README, `CHANGELOG.md` (Unreleased).
- `declare(strict_types=1);` v nových souborech, `final` u nových tříd, které nejsou k dědění, `readonly` vlastnosti u nových DTO.

## Peníze a data
- Částky pro výpočty vždy `int` v haléřích (`amountMinor`). Převod z API **přes string** (`MinorAmount::fromApi()`), nikdy `(int) ($float * 100)`.
- Datumy z API (`YYYY-MM-DD`) jako `CarbonImmutable` nebo string beze změny časové zóny.
- DTO transakce nese `raw` (původní pole z API), aby aplikace mohla uložit celou odpověď.

## Bezpečnost
- Token, `client_secret` ani access token se nesmí dostat do logů, zpráv výjimek, `payload` výjimek ani do DTO.
- Cache klíč access tokenu nesmí obsahovat secret v čitelné podobě (hash je v pořádku).

## Co balíček nedělá
- Žádné migrace, modely, UI, plánování ani párování plateb. Ukládání a párování patří do aplikace.
- Žádné odkazy na konkrétní aplikaci (it-elektro, ŘídímTo).
- Neodesílá platby bez podpisu – API to ani neumí, nic takového nesimuluj.

## Testy
- Po každé změně `composer test`. Všechny testy musí projít.
- HTTP vrstva přes `Http::fake()`, žádná skutečná volání Moneta API.
- Testy pojmenovávej `test_…` ve třídách (PHPUnit styl jako existující testy).
- Fixtures odpovědí odvozuj z příkladů ve specifikaci.

## Vydání
- Postup v `RELEASE_CHECKLIST.md`, release notes do `RELEASE_NOTES_vX.Y.Z.md`.
- `composer validate --strict` musí projít.
- Necommituj a netaguj. To dělá Petr.
