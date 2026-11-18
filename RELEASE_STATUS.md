# Experimental Release Status

## Current status

Paytakht Kala is an experimental open-source release candidate. It is suitable for source review and isolated development evaluation, not operational accounting or production deployment.

## Completed preparation

- Sanitized source candidate created without private Git history.
- Production environments, databases, operational data, uploads, backups, logs, and infrastructure-specific scripts excluded.
- Named-route contract reconciled with no unresolved literal route references in the static release check.
- Administrator middleware, active-account enforcement, record-level employee sale isolation, and denial-path tests added.
- Incomplete legacy workflows removed instead of being exposed through placeholder routes.
- Dependency license metadata reviewed for GPL-3.0-or-later compatibility.
- GitHub Actions configured for PHP, Laravel, SQLite migration, JavaScript, and Vite validation.

## Validation history

Earlier local preparation successfully ran the barcode-scanner JavaScript suite and a Vite production build. Those historical results do not validate the current commit. PHP and Composer were unavailable in the local preparation environment, so no current PHP/Laravel pass is claimed.

The authoritative validation result for the public release is the GitHub Actions run attached to the published commit.

## Remaining validation

- PHP syntax and Composer validation
- Laravel bootstrap and route enumeration
- PHPUnit authentication, authorization, CSRF, input, and cross-user access tests
- SQLite migrations and synthetic seeding
- JavaScript scanner tests and Vite build
- Disposable MySQL/MariaDB concurrency and financial-invariant testing
- Independent security and accounting review before any operational deployment

## Data policy

Use synthetic data only. Never open issues or attach test artifacts containing credentials, customer records, invoices, private uploads, production database contents, or operational logs.
