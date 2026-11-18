# Security Policy

## Supported versions

The initial `0.1.x` line is experimental and receives best-effort security fixes. No version is approved for production deployment. The support policy may change before 1.0.

## Reporting a vulnerability

Do not include credentials, personal data, financial records, or production database extracts in a report. Use GitHub private vulnerability reporting when it is enabled. Do not open a public issue for an unpatched vulnerability. If private reporting is unavailable, contact the maintainer through their GitHub profile without including exploit details in public text.

## Deployment requirements

- Generate a unique `APP_KEY`; never reuse the example configuration.
- Use unique database credentials with least privilege.
- Set `APP_ENV=production`, `APP_DEBUG=false`, secure session cookies, and HTTPS.
- Keep `.env`, source, private uploads, logs, and backups outside the public web root.
- Create real users manually with unique passwords; never use development seed data.
- Run migrations and tests only against a dedicated database.
- Preserve role and record-level authorization tests for every protected workflow.
- Establish encrypted backups, restore testing, dependency monitoring, and an incident-response process.

Passing automated tests or secret scans does not establish that the application is secure.
