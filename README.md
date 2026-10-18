# Laravel Production Doctor

A lightweight, read-only command-line diagnostic tool for Laravel deployment directories. It checks common production configuration risks and generates human-readable or JSON reports suitable for deployment and CI workflows.

> **Current release: v0.2.1.** Test in a disposable or staging environment first. This tool is not a substitute for a security audit, monitoring, or a full Laravel application boot test.

## What makes this tool different?

Laravel Production Doctor is a standalone CLI tool that does not boot the target Laravel application, execute its `artisan` command, or evaluate its `config/*.php` files. This provides a cautious first-pass inspection of a deployment directory while keeping the tool's execution surface small.

It is not intended to replace Laravel's first-party diagnostic tools or comprehensive application monitoring.

## Requirements

- PHP 8.2 or newer
- Composer
- A Laravel application directory containing `artisan` and `composer.json`
- Appropriate PDO driver extensions for any database connectivity checks you explicitly enable


## Usage

Inspect a Laravel application directory:

```bash
php bin/laravel-doctor --path=/path/to/laravel-app
```

On Windows:

```cmd
php bin/laravel-doctor --path="C:\path\to\laravel-app"
```

### JSON output

Generate a machine-readable diagnostic report:

```bash
php bin/laravel-doctor --path=/path/to/laravel-app --json
```

The JSON report contains project information, a generation timestamp, individual diagnostic results, and summary counts. Individual checks use the statuses `pass`, `warning`, `fail`, and `skip`.

The report structure is documented in [`docs/json-report-schema.json`](docs/json-report-schema.json).

### Network checks

Database and Redis connectivity checks are disabled by default. Enable them explicitly when needed:

```bash
php bin/laravel-doctor --path=/path/to/laravel-app --network
```

**Warning:** This option attempts database and Redis connections. Use it only when you intend to perform those connectivity checks.

### Exit codes

| Exit code | Meaning |
|---|---|
| `0` | No failed checks |
| `1` | One or more failed checks |
| `2` | Invalid arguments or path, or runtime setup error |

Unknown and duplicate options are rejected.

### Safety and limitations

- The tool is read-only and does not boot Laravel or modify application files.
- It does not execute the target application's `artisan` command or evaluate its `config/*.php` files.
- A successful report does not guarantee application security or production readiness.


## Development

Install dependencies:

```bash
composer install
```

Run PHP syntax checks:

```bash
composer check
```

Run PHPStan static analysis:

```bash
composer analyse
```

Run automated tests:

```bash
composer test
```

Run all local checks:

```bash
composer check && composer analyse && composer test
```

Validate Composer configuration:

```bash
composer validate
```

### Test coverage

The automated tests cover:

- CLI arguments, output, and exit codes
- Diagnostic checks and environment-file parsing
- Integration testing against a Laravel fixture
- JSON report structure and validation against `docs/json-report-schema.json`
- Rejection of invalid JSON reports

The test suite runs in GitHub Actions on PHP 8.2, 8.3, and 8.4.
