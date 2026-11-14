# 003 – profily, automatický sandbox token a výjimka NARR

## Cíl
Jedna aplikace může mít víc připojení (víc tokenů / účetních jednotek). Sandbox funguje bez ručního volání `withClientCredentials()`. Chyba dvoufázového ověření má vlastní výjimku.

## Kontext
- Config je dnes plochý (`moneta.api_token`, `moneta.client_id` …). Jiné credentials jdou jen přes `withContext([...])`.
- V sandboxu musí aplikace volat `Moneta::withClientCredentials()->accounts()->all()`. Bez toho jde požadavek bez `Authorization`.
- `401` s `errors[0].error = "NARR"` znamená, že požadovaná historie transakcí potřebuje dvoufázové ověření (popis endpointu transactions ve specifikaci).
- `throwForStatus()` dává do `payload` výjimky celou odpověď. U OAuth endpointu to nesmí obsahovat secret (odpověď ho neobsahuje, ale ověř testem, že request data se do výjimky nedostanou).

## Kroky
1. Config – nový klíč `profiles` a `default_profile`:
   ```php
   'default_profile' => env('MONETA_PROFILE', 'default'),
   'profiles' => [
       // 'firma-b' => ['api_token' => ..., 'sandbox' => false, 'identification_number' => ...],
   ],
   ```
   Profil `default` = stávající plochá konfigurace (zpětná kompatibilita). Pojmenovaný profil přebíjí ploché klíče jen v tom, co sám definuje.
2. `MonetaManager::profile(string $name): static` – klon s nastaveným profilem (`resolveConfig()` čte nejdřív `overrides`, pak profil, pak ploché klíče). Neznámý profil → `InvalidArgumentException`. Facade `@method`.
3. Automatický token: když není `Authorization` z `withToken()`, není `api_token` a profil má `client_id` + `client_secret`, `rawRequest()` si sám vyžádá access token přes `issueAccessToken()` (cache už existuje). `withClientCredentials()` zůstává funkční.
4. Při `401` na API volání se sandbox access tokenem: smazat token z cache a **jednou** zopakovat. Druhá `401` → výjimka.
5. `TwoFactorRequiredException extends UnauthorizedException` pro `401` s kódem `NARR`.
6. Test, že `client_secret` a `api_token` nejsou v `getMessage()` ani `payload` žádné výjimky.
7. README: sekce Profiles a zjednodušený Sandbox příklad. `CHANGELOG.md` Unreleased.

## Nedělat
- Neměnit význam plochých config klíčů.
- Neukládat tokeny do databáze.

## Hotovo když (`composer test`)
- `Moneta::profile('b')->accounts()->all()` posílá token profilu `b`.
- Bez profilu se použije plochá konfigurace (stávající testy projdou).
- Sandbox (jen client id/secret) → první volání si vyžádá token, druhé ho vezme z cache (1× token endpoint).
- `401` → obnova tokenu a jedno opakování.
- `401 NARR` → `TwoFactorRequiredException`.

## Po dokončení
- Stav do `.ai/tasks/README.md`.
