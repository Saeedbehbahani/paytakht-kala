# Contributing

## Development workflow

1. Fork the public repository and create a focused branch.
2. Use synthetic data only.
3. Add tests for financial invariants, authorization denials, and failure paths.
4. Run the PHP tests, JavaScript tests, frontend build, formatting check, and secret scan.
5. Document schema or accounting-rule changes in the pull request.
6. Keep pull requests small enough for financial and authorization behavior to be reviewed independently.

## Pull requests

Explain the problem, intended behavior, security or accounting impact, and checks run. Never weaken or delete a meaningful security test simply to make CI pass. New protected functionality should include allowed-path and denial-path tests.

Never commit environment files, credentials, server addresses, database exports, real inventory, customer information, invoices, private uploads, logs, backups, or generated release archives.

Changes to money, inventory, migrations, authentication, authorization, or file access require focused review. Database concurrency changes must be verified on a disposable MySQL/MariaDB instance in addition to SQLite tests.
