# Release Checklist

## Before Tagging

- Confirm package versioning strategy for the release.
- Review `README.md` for any newly added public API or config keys.
- Review `composer.json` metadata, keywords and support links.
- Ensure no local-only files are intended for distribution.

## Validation

- Run `composer validate --strict`.
- Run `composer test`.
- Run `composer outdated --direct` and confirm direct dependencies are current.

## Compatibility

- Laravel support target: 11, 12, 13.
- PHP support target: 8.2, 8.3, 8.4, 8.5.
- Verify package behavior against at least one supported Laravel app before release.
- Testbench matrix used for package development:
  - `orchestra/testbench:^9` for Laravel 11
  - `orchestra/testbench:^10` for Laravel 12
  - `orchestra/testbench:^11` for Laravel 13

## Moneta Coverage

- Verify AISP flows you changed: accounts, balances, transactions, statements (list, download, file).
- Verify authentication if touched: static bearer token and OAuth client credentials, including the sandbox scope.
- Verify error mapping if HTTP status or error payload handling changed.

## Release

- Update changelog or release notes summary.
- Commit the final diff.
- Tag the release.
- Publish the release notes.
- If applicable, submit or refresh the package on Packagist.
