# Paytakht Kala

Paytakht Kala is an experimental open-source accounting, inventory, sales, and small-business management application built with Laravel. It provides a Persian-language, server-rendered interface for retail operations while keeping financial calculations and inventory mutations on the server.

> [!WARNING]
> This is an experimental release for development and evaluation. The Laravel runtime and security test suite must complete on a clean CI runner before operational use. Do not use real financial, customer, or inventory data until CI passes and the deployment receives independent security and accounting review.

## Features

- Session authentication with active-account enforcement
- Administrator and employee roles
- Record-level isolation for employee sales
- Sales entry with integer toman amounts and inventory updates
- Administrator purchase and stock-entry workflows
- Product catalog, barcode lookup, inventory history, and stock adjustments
- Expense recording and controlled void operations
- Administrative settings, employee management, audit records, and exception review
- Opening inventory and opening-obligation workflows
- Private document uploads stored outside the public web root
- Persian Blade interface with Vite-built JavaScript and CSS

Incomplete legacy cheque, closing, installment, payroll-mutation, finance-mutation, and stock-count workflows are intentionally not exposed. They must not be reintroduced without complete implementations and authorization tests.

## Technology

- PHP 8.3+ and Laravel 13
- Blade and Laravel session authentication
- SQLite for local evaluation; MySQL/MariaDB for isolated development environments
- JavaScript, Vite 8, Tailwind CSS, and ZXing barcode support
- PHPUnit 12

See [ARCHITECTURE.md](ARCHITECTURE.md) for application structure and security boundaries.

## Requirements

- PHP 8.3 or later with PDO, SQLite or MySQL, Mbstring, OpenSSL, and other Laravel-required extensions
- Composer 2
- Node.js 24 or another version supported by the locked Vite release
- npm
- SQLite for the default local setup, or an isolated MySQL/MariaDB database

## Installation

```sh
git clone https://github.com/Saeedbehbahani/paytakht-kala.git
cd paytakht-kala
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm ci
npm run build
php artisan serve
```

On Windows PowerShell, create the SQLite file with:

```powershell
New-Item -ItemType File database/database.sqlite
```

The development seeder uses synthetic data, refuses to run in production, and generates a random one-time demo password printed only to the local console.

## Independent database configuration

The default `.env.example` uses SQLite. For disposable MySQL/MariaDB development, set `DB_CONNECTION=mysql` and provide a new database name and least-privilege development credentials. Never reuse production credentials or point migrations, seeders, or tests at an operational database.

## Testing

```sh
composer validate --strict
php artisan migrate:fresh --seed
php artisan route:list
php artisan test
vendor/bin/pint --test
npm run test:scanner
npm run build
```

Reliability scripts in `tests/Reliability` require a disposable MySQL/MariaDB database and must never target production. GitHub Actions uses isolated synthetic data and does not connect to external infrastructure.

## Security

This repository contains no production environment, operational database, customer records, private uploads, backups, deployment credentials, or original private Git history. Review [SECURITY.md](SECURITY.md) before testing or deployment. Do not report unpatched vulnerabilities in public issues.

Passing automated tests does not make an accounting application production-ready. Operators remain responsible for independent security review, accounting validation, backups, recovery testing, and compliance with local law.

## Contributing

Contributions are welcome, especially focused tests and reviews for authorization, financial invariants, inventory concurrency, accessibility, localization, and documentation. Read [CONTRIBUTING.md](CONTRIBUTING.md) before opening a pull request.

## Release maturity

The first public version is planned as `v0.1.0-alpha.1` and will be labeled **Experimental**. Known limitations and verification status are recorded in [CHANGELOG.md](CHANGELOG.md) and [RELEASE_STATUS.md](RELEASE_STATUS.md).

## License

The original Paytakht Kala code and owner-created brand assets are proposed for release under the GNU General Public License v3.0 or later (`GPL-3.0-or-later`). Dependencies retain their respective licenses. See [LICENSE](LICENSE) and [LICENSE-REVIEW.md](LICENSE-REVIEW.md).
