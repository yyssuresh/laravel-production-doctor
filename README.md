# Laravel Production Doctor

A read-only command-line diagnostic tool for Laravel deployments. It checks common production configuration risks and emits either a human-readable report or JSON suitable for CI and deployment workflows.

> **Status:** Initial development release. Use in staging first. This tool is not a substitute for a security audit or monitoring system.

## Requirements

- PHP 8.2 or newer
- Composer
- A Laravel application directory containing `artisan` and `composer.json`
- PDO driver extensions for the database connectivity check you want to use

## Install for development

```bash
git clone https://github.com/yyssuresh/laravel-production-doctor.git
cd laravel-production-doctor
composer install
composer test
```

## Run against a Laravel app

From the tool repository:

```bash
php bin/laravel-doctor --path=/absolute/path/to/your-laravel-app
php bin/laravel-doctor --path=/absolute/path/to/your-laravel-app --json
php bin/laravel-doctor --path=/absolute/path/to/your-laravel-app --no-network
```

After packaging/releasing, Composer exposes the `laravel-doctor` binary when the package is installed in a consuming project.

## Checks included

- Laravel project markers (`artisan`, `composer.json`)
- Presence of a readable `.env` file (environment variables may instead be injected by the platform)
- `APP_DEBUG` and `APP_ENV` production configuration
- Presence of `APP_KEY` without printing its value
- CLI PHP version and common PHP extensions
- Existence of Laravel's configuration cache file
- Basic `.env` write-permission review on Unix-like systems
- Optional database connection check for MySQL/MariaDB, PostgreSQL, or SQLite
- Optional Redis TCP reachability check

The Redis check confirms only that the TCP endpoint is reachable; it does **not** validate Redis authentication, TLS, or commands. Database checks are best-effort and can produce warnings when the current environment cannot reach the configured service.

## Safety and limitations

- Does not boot Laravel, execute `artisan`, or evaluate `config/*.php` files.
- Does not modify application files or configuration.
- Does not intentionally print environment values, database credentials, or application keys.
- Network checks can contact the database and Redis endpoints configured in `.env`. Use `--no-network` to disable these checks.
- The `.env` parser supports ordinary `KEY=value` lines and simple quoting; it is not a full implementation of every dotenv edge case.
- A present configuration cache file does not prove that it is fresh or correct.
- File permissions vary by operating system and deployment model.
- The first release does not inspect live HTTP headers, TLS certificates, queue workers, scheduler health, or database schema migrations.

## Exit codes

- `0`: no failed checks (warnings may still exist)
- `1`: one or more failed checks
- `2`: invalid path or missing installed dependencies

## Development

```bash
composer test
composer check
```

Contributions should include tests and documentation for new checks. Avoid logging or including secret values in diagnostics or issue reports.

## Security reports

Please do not publish exploitable vulnerabilities with secrets or production details in a public issue. Until a private security contact is configured, contact the maintainer through their GitHub profile and share only the minimum details needed to coordinate a fix.

## License

MIT. See [LICENSE](LICENSE).
