# moneta-laravel-package

Lightweight Laravel package for Moneta Money Bank VIP REST API integration.

## Installation

```bash
composer require sloukapetr/moneta-laravel-package
```

Publish the package configuration file:

```bash
php artisan vendor:publish --tag=moneta-config
```

This creates [config/moneta.php](config/moneta.php).

## Environment Variables

Use this complete `.env` block:

```env
MONETA_BASE_URL=https://api.moneta.cz

# Static bearer token mode
MONETA_API_TOKEN=

# OAuth client credentials mode
MONETA_CLIENT_ID=
MONETA_CLIENT_SECRET=
MONETA_OAUTH_SCOPE=
MONETA_OAUTH_TOKEN_URL=https://api.moneta.cz/auth/oauth/v2/token

# Optional context headers
MONETA_IDENTIFICATION_NUMBER=
MONETA_BUSINESS_NAME=
MONETA_APPLICATION_NAME=Laravel Package

# Optional flag
MONETA_SANDBOX=true

# Named connection. `default` uses the flat keys above.
MONETA_PROFILE=default
```

If `MONETA_OAUTH_SCOPE` is empty, the package uses `vip_sandbox` automatically when `MONETA_SANDBOX=true`.

## Configuration

Default config keys in [config/moneta.php](config/moneta.php):

```php
return [
    'default_profile' => env('MONETA_PROFILE', 'default'),
    'profiles' => [
        // 'firma-b' => ['api_token' => env('MONETA_FIRMA_B_TOKEN'), 'sandbox' => false, 'identification_number' => env('MONETA_FIRMA_B_ID')],
    ],
    'sandbox' => env('MONETA_SANDBOX', true),
    'api_token' => env('MONETA_API_TOKEN'),
    'client_id' => env('MONETA_CLIENT_ID'),
    'client_secret' => env('MONETA_CLIENT_SECRET'),
    'oauth_scope' => env('MONETA_OAUTH_SCOPE'),
    'oauth_token_url' => env('MONETA_OAUTH_TOKEN_URL', 'https://api.moneta.cz/auth/oauth/v2/token'),
    'application_name' => env('MONETA_APPLICATION_NAME', 'Laravel Package'),
    'identification_number' => env('MONETA_IDENTIFICATION_NUMBER'),
    'business_name' => env('MONETA_BUSINESS_NAME'),
    'base_url' => env('MONETA_BASE_URL', 'https://api.moneta.cz'),
    'timeout' => 15,
    'retry' => 3,
    'retry_sleep' => 100,
];
```

## Usage

```php
use SloukaPetr\Moneta\Facades\Moneta;

$accounts = Moneta::accounts()->all();
$balance = Moneta::accounts()->getBalance($accountId);
$transactions = Moneta::transactions()->all($accountId);
$statements = Moneta::statements()->list($accountId);
```

## Authentication

### Option 1: Static Token

Set `MONETA_API_TOKEN` and call the API directly:

```php
$accounts = Moneta::accounts()->all();
```

### Option 2: OAuth Client Credentials

Use `MONETA_CLIENT_ID` and `MONETA_CLIENT_SECRET` and let the package request a token. The token is cached until `expires_in` minus 30 seconds, separately for each client, scope and token URL.

`withClientCredentials()` still requests the token explicitly:

```php
$accounts = Moneta::withClientCredentials()->accounts()->all();
```

You can also override credentials at runtime:

```php
$accounts = Moneta::withContext([
    'client_id' => 'your-client-id',
    'client_secret' => 'your-client-secret',
    'oauth_scope' => 'vip_sandbox',
])->withClientCredentials()->accounts()->all();
```

## Profiles

`default` is the flat configuration above. A named profile replaces only the keys it defines. `Moneta::profile()` selects one for a single call chain:

```php
$accounts = Moneta::profile('firma-b')->accounts()->all();
```

Set `MONETA_PROFILE=firma-b` to use that profile without calling `profile()`.

## Sandbox Notes

With `MONETA_SANDBOX=true` and only a client id and secret, the first call requests an access token. The next call reuses the cached token. A `401` drops that token and retries the API call once.

```php
$accounts = Moneta::accounts()->all();
```

- Sandbox returns predefined mock data.
- Most parameters are not validated the same way as in production.
- `/api/v4/vip/aisp/my/accounts/statement` returns XML in sandbox mode.
- HTTP 401 with error `NARR` means the requested transaction history needs two-factor verification (`TwoFactorRequiredException`).

## Statement Download Flow

1. List available statements:

```php
$statements = Moneta::statements()->list($accountId, ['from' => '2026-01-01', 'to' => '2026-01-31']);
```

2. Download statement data (JSON or file metadata):

```php
$download = Moneta::statements()->download($accountId, $statementId, 'JSON');
```

3. For file formats, get URL and download file content:

```php
$url = Moneta::statements()->downloadUrl($accountId, $statementId, 'PDF');
$file = Moneta::statements()->downloadFile($fileId);
```

## Transactions

Booked incoming payments expose the amount in minor units (haléře), payment symbols and the counterparty taken from the debtor:

```php
foreach (Moneta::transactions()->all($accountId) as $transaction) {
    if (! $transaction->isSettledIncomingPayment()) {
        continue;
    }

    $transaction->amountMinor;
    $transaction->symbols->vs;
}
```

`$transaction->amount` and `$balance->value` remain available and are deprecated in favour of `amountMinor`.

`between()` walks the inclusive `valueDate` range without loading every page first. `cursor()` does the same for any filters. Both request the API only while you iterate:

```php
$transactions = Moneta::transactions()->between($accountId, '2026-01-01', '2026-01-31');

foreach ($transactions as $transaction) {
    $transaction->amountMinor;
}
```

History is at most two years.

## Token

`introspect()` reads the expiration of a static API token (`GET /api/v1/vip/token/introspect`):

```php
$token = Moneta::token()->introspect();

$token->expiresAt;
$token->daysUntilExpiry();
```

HTTP 403 means the token is not a Moneta API token.

## Testing

```bash
composer test
```

`Moneta::fake()` without arguments swaps in a local client. It does not send HTTP:

```php
$fake = Moneta::fake();

$fake->assertNothingFetched();
$fake->incomingPayment($accountId, 150000, '2026001');

$transactions = Moneta::transactions()->all($accountId);

$fake->assertTransactionsFetched($accountId);
```

`withAccount()`, `withTransaction()`, `withBalance()` and `withTokenExpiration()` build the same response shapes the real API uses. `failWith(401, 'NARR')` fails the following calls. A non-empty argument still goes to `Http::fake()`:

```php
Moneta::fake([
    'https://api.moneta.cz/api/v4/vip/aisp/my/accounts*' => Http::response(['accounts' => []], 200),
]);
```

