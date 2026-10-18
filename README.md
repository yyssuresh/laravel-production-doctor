# Laravel Production Doctor

A lightweight, read-only command-line diagnostic tool for Laravel deployment directories. It inspects common production configuration risks and emits a human-readable report or JSON suitable for CI.

> **Status: v0.2.1 development update (not yet released).** Test in a disposable or staging environment first. This tool is not a substitute for a security audit, monitoring, or a full Laravel boot test.

## What makes this tool different?

Laravel Production Doctor is a standalone CLI that does **not** boot the target Laravel application, execute its `artisan` command, or evaluate its `config/*.php` files. That makes it useful for a cautious first-pass inspection of a deployment directory. It deliberately trades depth for a smaller execution surface; it is not intended to replace Laravel's first-party Doctor package or other full application diagnostics.

## Requirements

- PHP 8.2 or newer
- Composer
- A Laravel application directory containing `artisan` and `composer.json`
- PDO driver extensions for any database connectivity check you explicitly enable

## Install for development

```bash
git clone https://github.com/yyssuresh/laravel-production-doctor.git
cd laravel-production-doctor
composer install
composer check
composer test
```

## Run against a Laravel app

From this repository:

```bash
# Safe default: no database or Redis connection attempts
php bin/laravel-doctor --path=/absolute/path/to/your-laravel-app

# Machine-readable report; still no network connection attempts
php bin/laravel-doctor --path=/absolute/path/to/your-laravel-app --json

# Explicitly opt in to database and Redis connectivity attempts
php bin/laravel-doctor --path=/absolute/path/to/your-laravel-app --network
```

Network checks are **disabled by default**. Use `--network` only when you intend to contact services configured for the target application and have permission to do so. The tool does not authenticate to Redis or issue Redis commands; it only checks TCP reachability. Database connection checks are best-effort and can generate warnings when the service is unreachable or the relevant PDO driver is unavailable.

## Checks included

- Laravel project markers (`artisan`, `composer.json`)
- `.env` presence and readable configuration
- Effective `APP_DEBUG`, `APP_ENV`, and `APP_KEY` values, with process environment values taking precedence over `.env`
- CLI PHP version and common PHP extensions
- Existence of Laravel's configuration cache file (not its freshness or correctness)
- Basic `.env` write-permission review on Unix-like systems
- Opt-in database connectivity for MySQL/MariaDB, PostgreSQL, or SQLite
- Opt-in Redis TCP reachability

## Environment file parsing

The parser supports ordinary `KEY=value` assignments, optional `export`, inline comments outside quotes, single- and double-quoted values, common double-quoted escapes, and multiline quoted values. Malformed quoted values and unexpected trailing text after quoted values are ignored; an unterminated quote does not consume subsequent assignment lines. It does not execute shell expressions or expand `${VARIABLE}` references, and it is not a full replacement for Laravel's dotenv implementation. Process environment values override values parsed from `.env` to support container and hosting-platform deployments. Tests can inject an environment snapshot to avoid dependence on the machine running them.

## Safety and limitations

- Does not boot Laravel, execute `artisan`, or evaluate `config/*.php` files.
- Does not modify target application files or configuration.
- Does not intentionally print environment values, database credentials, or application keys.
- Network checks are disabled unless `--network` is supplied. When enabled, the tool contacts configured database/Redis endpoints; inspect the target `.env` and environment first. Redis hosts must be conventional hostnames or IP addresses; URI schemes and stream-wrapper syntax are rejected.
- Connection timeout behavior depends partly on the PDO driver and operating system; do not treat it as a hard upper bound for every DNS or network condition.
- A reachable Redis TCP port does not prove authentication, TLS, or command execution works.
- A present configuration cache file does not prove it is fresh or correct.
- File permission semantics vary by operating system and deployment model.
- The tool does not inspect live HTTP headers, TLS certificates, queue workers, scheduler health, or migration status.

## Exit codes

- `0`: no failed checks (warnings may still exist)
- `1`: one or more failed checks
- `2`: invalid path, missing dependencies, or report encoding failure

## Development

```bash
composer check
composer test
```

Contributions should include tests and documentation for new checks. Never include `.env` contents, credentials, tokens, application keys, or sensitive production details in issues or pull requests.

## Security reports

Please do not publish exploitable vulnerabilities together with secrets or production details in a public issue. Until a private security contact is configured, contact the maintainer through their GitHub profile and share only the minimum details needed to coordinate a fix.

## License

MIT. See [LICENSE](LICENSE).
