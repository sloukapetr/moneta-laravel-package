# Agent Instructions for moneta-laravel-package

## Purpose
Laravel package for the Moneta Money Bank business API (VIP API): own accounts, balances, transactions and statements; later payment preparation for signing in Internet Banking.

## Source of Truth
- `.ai/specs/moneta/*.json` – OpenAPI specifications from the Moneta API portal.
- `src/MonetaManager.php` – the only transport layer.
- `src/Services/` – endpoint mapping, `src/Data/` – DTOs.
- `tests/` – the contract for supported behavior.

## Working Rules
- Amounts for calculations are `int` minor units (`amountMinor`), converted from the API via string, never via float math.
- Never leak the token, client secret or access token into logs, exceptions or DTOs.
- No migrations, models, UI or payment matching in this package.
- Preserve public API compatibility (SemVer); deprecate instead of removing.

## Testing Rules
- Run `composer test` after code changes. Use `Http::fake()`; no real API calls.

## Detailed Rules and Tasks
- Detailed rules (Czech) are in `.ai/rules/package.md`; the index is `.ai/rules/index.md`. They take precedence over this summary.
- Work items are in `.ai/tasks/NNN-*.md`; status overview in `.ai/tasks/README.md`. Update the status when a task is done.
- Do not commit or tag; the maintainer does that.
