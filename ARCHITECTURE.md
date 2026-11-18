# Architecture

## Runtime

The application is a Laravel 13 server-rendered monolith. Blade renders authenticated pages, application services implement accounting and inventory rules, Eloquent models and query-builder operations persist data, and Vite builds browser assets. No Redis, container runtime, or permanent queue worker is required by the candidate's default local configuration.

## Main areas

- `app/Http/Controllers`: HTTP validation and workflow coordination.
- `app/Services`: sales, purchases, inventory, costing, reporting, and financial operations.
- `app/Models`: domain persistence models.
- `database/migrations`: schema and accounting constraints.
- `resources/views`: Persian Blade interface.
- `resources/js` and `resources/css`: browser source.
- `routes/web.php`: authenticated application endpoints.
- `tests`: unit, feature, workflow, JavaScript, and opt-in reliability checks.

## Security boundaries

Authentication uses Laravel sessions. Active-account enforcement applies to authenticated routes. Managerial reports, purchases, inventory mutations, expenses, product cost/history details, opening data, parties, private uploads, settings, employee management, audit data, and exception review are restricted to the `ADMIN` role. Employees may create sales and can list or open only sales they own. State-changing requests use Laravel CSRF protection and controller validation. Private business files remain on a non-public filesystem disk and must never be exposed through a public storage symlink.

Authorization changes require allowed-path and denial-path regression tests. Unknown policy abilities should remain denied by default.

## Financial integrity

Money is represented as integer toman values. Core services perform server-side calculation, database transactions, row locking, idempotency checks, and ledger updates. Database-specific concurrency and signed-arithmetic behavior must be tested against the exact target MySQL/MariaDB version before operational use.
