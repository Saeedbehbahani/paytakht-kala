# Changelog

All notable changes to Paytakht Kala will be documented in this file. Pre-1.0 releases may change application and database contracts.

## [Unreleased]

- Awaiting first public CI validation and experimental release.

## [0.1.0-alpha.1] - 2026-11-18

### Added

- Initial sanitized open-source source tree without private Git history or operational data.
- Administrator and employee authorization boundaries.
- Record-level employee sale isolation and security regression tests.
- GitHub Actions validation for PHP, Laravel, SQLite migrations, JavaScript tests, and Vite.
- Public installation, security, contribution, architecture, and release documentation.

### Changed

- Demo credentials are generated randomly during local seeding.
- Employee navigation exposes only authorized workflows.

### Removed

- Incomplete cheque, daily closing, installment, payroll-mutation, finance-mutation, and stock-count interfaces.
- Production environments, databases, customer data, uploads, backups, logs, infrastructure scripts, and private Git history.

### Known limitations

- Experimental; not approved for operational accounting or production deployment.
- PHP/Laravel runtime validation must complete in GitHub Actions.
- MySQL/MariaDB concurrency and accounting invariants require an isolated disposable database.

[Unreleased]: https://github.com/Saeedbehbahani/paytakht-kala/compare/v0.1.0-alpha.1...HEAD
[0.1.0-alpha.1]: https://github.com/Saeedbehbahani/paytakht-kala/releases/tag/v0.1.0-alpha.1
