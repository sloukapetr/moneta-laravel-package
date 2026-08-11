# moneta-laravel-package

Lightweight Laravel package for Moneta Money Bank VIP REST API integration.

## Instalace

```bash
composer require sloukapetr/moneta-laravel-package
```

## Konfigurace

Přidejte do svého souboru config/services.php nebo config/moneta.php vlastní hodnoty:

```php
return [
    'moneta' => [
        'base_url' => env('MONETA_BASE_URL', 'https://api.moneta.cz'),
        'api_token' => env('MONETA_API_TOKEN'),
    'client_id' => env('MONETA_CLIENT_ID'),
    'client_secret' => env('MONETA_CLIENT_SECRET'),
    'oauth_scope' => env('MONETA_OAUTH_SCOPE', 'vip_sandbox'),
    'oauth_token_url' => env('MONETA_OAUTH_TOKEN_URL', 'https://api.moneta.cz/auth/oauth/v2/token'),
        'identification_number' => env('MONETA_IDENTIFICATION_NUMBER'),
        'business_name' => env('MONETA_BUSINESS_NAME'),
        'application_name' => env('MONETA_APPLICATION_NAME'),
    ],
];
```

## Použití

```php
use SloukaPetr\Moneta\Facades\Moneta;

$accounts = Moneta::accounts()->all();
$balance = Moneta::accounts()->getBalance($accountId);
$transactions = Moneta::transactions()->get($accountId);
$statements = Moneta::statements()->list($accountId);
```

## Autorizace

Moneta API používá autentizaci pomocí tokenu. Každý požadavek musí obsahovat hlavičku:

```http
Authorization: Bearer {access_token}
```

Token lze předat buď přes konfigurační hodnotu `api_token`, nebo přes metodu `withToken()`:

```php
Moneta::withToken('your-access-token')->accounts()->all();
```

Případně lze token načíst automaticky přes OAuth2 client credentials:

```php
Moneta::withContext([
  'client_id' => env('MONETA_CLIENT_ID'),
  'client_secret' => env('MONETA_CLIENT_SECRET'),
  'oauth_scope' => env('MONETA_OAUTH_SCOPE', 'vip_sandbox'),
])->withClientCredentials()->accounts()->all();
```

### Sandbox

Pro testování v sandboxu je nutné požádat o přístup k sandbox API plánu a přidat scope `vip_sandbox` do aplikace. Poté lze vygenerovat token pomocí OAuth2 client credentials:

```bash
curl --location 'https://api.moneta.cz/auth/oauth/v2/token' \
  --data-urlencode 'client_id=abcdefgh123abcd' \
  --data-urlencode 'client_secret=ijklmnopq456ijkl' \
  --data-urlencode 'grant_type=client_credentials' \
  --data-urlencode 'scope=vip_sandbox'
```

Odpověď obsahuje `access_token`, který se použije stejně jako běžný token Moneta API.

### Omezení sandboxu

Moneta API sandbox je určená k seznámení s API a obsahuje stejné endpointy a parametry jako produkční API. To je ideální pro vývojáře aplikací.

Mezi hlavní omezení patří:

- sandbox vrací předdefinovaná testovací data,
- většina parametrů není sandboxem validována, takže neplatné hodnoty mohou stále vrátit předdefinovanou odpověď,
- endpoint `/api/v4/vip/aisp/my/accounts/statement` vrací výpis v XML formátu bez ohledu na zvolený formát v `/api/v4/vip/aisp/my/accounts/{id}/statement/download`.

## Začínáme

Po nastavení autorizace můžete začít s voláním Moneta API. V balíčku je nejjednodušší začít seznamem účtů:

```php
use SloukaPetr\Moneta\Facades\Moneta;

$accounts = Moneta::accounts()->all();
```

Pokud chcete použít přímo endpoint Moneta API, odpovídá tomu požadavek:

```http
GET https://api.moneta.cz/api/v4/vip/aisp/my/accounts
```

Příklad v cURL:

```bash
curl --location 'https://api.moneta.cz/api/v4/vip/aisp/my/accounts' \
  --header 'Authorization: Bearer your-access-token'
```

V balíčku už není nutné hlavičku ručně sestavovat — stačí nastavit `api_token` v konfiguraci nebo použít `Moneta::withToken('...')`.

## Vývojové doporučení

Při vývoji integrace s Moneta API doporučujeme postupovat takto:

- nejdříve vyvíjet v sandboxu a zkoušet hlavní happy-path scénáře,
- pro širší testování vytvořit testovací uživatele v internetovém bankovnictví,
- po důkladném otestování připravit integraci pro ostré nasazení.

## Průvodce stažením výpisů

Proces stažení výpisů se skládá z několika kroků:

1. Nejprve načtěte seznam dostupných výpisů pro účet.
2. Z výsledku použijte `accountStatementId` pro stažení konkrétního výpisu.
3. Pro stažení obsahu výpisu použijte endpoint `/api/v4/vip/aisp/my/accounts/{accountId}/statement/download`.
4. Pokud je vyžadován formát souboru (PDF, XML, ABO), použijte vrácenou URL z `dataDownloadURL` a stáhněte soubor přes endpoint `/api/v4/vip/aisp/my/accounts/statement`.

### Příklad v balíčku

```php
use SloukaPetr\Moneta\Facades\Moneta;

$statements = Moneta::statements()->list($accountId);
$download = Moneta::statements()->download($accountId, $statements[0]->accountStatementId, 'JSON');
$url = Moneta::statements()->downloadUrl($accountId, $statements[0]->accountStatementId, 'PDF');
```

## Příklady

### Stažení statementu jako soubor

```php
$content = Moneta::statements()->downloadFile('file-123');
```

### Získání URL pro stažení statementu

```php
$url = Moneta::statements()->downloadUrl($accountId, $statementId);
```

## Testy

```bash
php vendor/bin/pest
```

