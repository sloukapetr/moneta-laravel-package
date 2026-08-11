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
```

If `MONETA_OAUTH_SCOPE` is empty, the package uses `vip_sandbox` automatically when `MONETA_SANDBOX=true`.

## Configuration

Default config keys in [config/moneta.php](config/moneta.php):

```php
return [
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
];
```

## Usage

```php
use SloukaPetr\Moneta\Facades\Moneta;

$accounts = Moneta::accounts()->all();
$balance = Moneta::accounts()->getBalance($accountId);
$transactions = Moneta::transactions()->get($accountId);
$statements = Moneta::statements()->list($accountId);
```

## Authentication

### Option 1: Static Token

Set `MONETA_API_TOKEN` and call the API directly:

```php
$accounts = Moneta::accounts()->all();
```

### Option 2: OAuth Client Credentials

Use `MONETA_CLIENT_ID` and `MONETA_CLIENT_SECRET` and let the package request a token:

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

## Sandbox Notes

- Sandbox returns predefined mock data.
- Most parameters are not validated the same way as in production.
- `/api/v4/vip/aisp/my/accounts/statement` returns XML in sandbox mode.

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

## Testing

```bash
php vendor/bin/pest
```

