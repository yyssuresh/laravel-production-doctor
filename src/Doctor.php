<?php

declare(strict_types=1);

namespace LaravelProductionDoctor;

use LaravelProductionDoctor\Support\EnvFile;
use PDO;
use Throwable;

final class Doctor
{
    /**
     * Network connectivity checks are disabled by default. Pass true only when
     * the operator explicitly opts in with --network.
     */
    public function __construct(private readonly string $projectPath, private readonly bool $networkChecks = false) {}

    /** @return array{project:string,generated_at:string,checks:list<array{name:string,status:string,message:string,recommendation:?string}>,summary:array{pass:int,warning:int,fail:int,skip:int}} */
    public function run(): array
    {
        $root = rtrim($this->projectPath, DIRECTORY_SEPARATOR);
        if ($root === '') {
            $root = DIRECTORY_SEPARATOR;
        }

        $checks = [];
        $isLaravel = is_file($root . '/artisan') && is_file($root . '/composer.json');
        $checks[] = new CheckResult(
            'Laravel project',
            $isLaravel ? 'pass' : 'fail',
            $isLaravel ? 'Laravel project markers were found.' : 'Could not find both artisan and composer.json.',
            $isLaravel ? null : 'Run this command from a Laravel application directory or use --path.',
        );

        if (!$isLaravel) {
            return $this->report($checks);
        }

        $envPath = $root . '/.env';
        $envExists = is_file($envPath);
        $fileValues = EnvFile::fromFile($envPath);
        $env = EnvFile::withProcessEnvironment($fileValues);

        $checks[] = new CheckResult(
            '.env file',
            $envExists ? 'pass' : 'warning',
            $envExists ? 'An environment file exists.' : 'No .env file was found; environment may be injected externally.',
            $envExists ? null : 'Confirm production environment variables are supplied by your deployment platform.',
        );

        $debug = strtolower(trim($env['APP_DEBUG'] ?? ''));
        if (in_array($debug, ['true', '1', 'yes', 'on'], true)) {
            $checks[] = new CheckResult('Debug mode', 'fail', 'APP_DEBUG appears to be enabled.', 'Set APP_DEBUG=false in production and clear/rebuild the configuration cache during your normal deployment process.');
        } elseif (in_array($debug, ['false', '0', 'no', 'off'], true)) {
            $checks[] = new CheckResult('Debug mode', 'pass', 'APP_DEBUG is disabled.');
        } else {
            $checks[] = new CheckResult('Debug mode', 'warning', 'APP_DEBUG is missing or could not be interpreted.', 'Set APP_DEBUG=false explicitly in production or confirm the value is injected by the hosting platform.');
        }

        $appEnv = strtolower(trim($env['APP_ENV'] ?? ''));
        $checks[] = match ($appEnv) {
            'production', 'prod' => new CheckResult('Application environment', 'pass', 'APP_ENV is set to production.'),
            '' => new CheckResult('Application environment', 'warning', 'APP_ENV is not available from .env or the process environment.', 'Set APP_ENV=production, or confirm the deployment platform injects it.'),
            default => new CheckResult('Application environment', 'warning', 'APP_ENV is set to a non-production value.', 'Confirm this is intentional for the environment being checked.'),
        };

        $key = trim($env['APP_KEY'] ?? '');
        $checks[] = new CheckResult(
            'Application key',
            $key !== '' ? 'pass' : 'fail',
            $key !== '' ? 'APP_KEY is present (value intentionally hidden).' : 'APP_KEY is not available from .env or the process environment.',
            $key !== '' ? null : 'Generate and configure an application key through your normal secure deployment process; never share it in logs.',
        );

        $phpOk = PHP_VERSION_ID >= 80200;
        $checks[] = new CheckResult(
            'PHP version',
            $phpOk ? 'pass' : 'fail',
            'CLI PHP version is ' . PHP_VERSION . '.',
            $phpOk ? null : 'Use PHP 8.2 or newer for this tool and verify the version required by your Laravel release.',
        );
        foreach (['ctype', 'filter', 'mbstring', 'openssl', 'pdo', 'tokenizer', 'xml'] as $extension) {
            $loaded = extension_loaded($extension);
            $checks[] = new CheckResult('PHP extension: ' . $extension, $loaded ? 'pass' : 'warning', $loaded ? 'Extension is loaded.' : 'Extension is not loaded.', $loaded ? null : 'Install/enable this extension if it is required by your Laravel application or dependencies.');
        }

        $configCache = $root . '/bootstrap/cache/config.php';
        $cacheExists = is_file($configCache);
        $checks[] = new CheckResult(
            'Configuration cache',
            $cacheExists ? 'pass' : 'warning',
            $cacheExists ? 'A configuration cache file exists; its freshness was not verified.' : 'No configuration cache file was found.',
            $cacheExists ? 'Confirm your deployment process refreshes cached configuration after environment changes.' : 'For production, consider caching configuration as part of your deployment process.',
        );

        if ($envExists && DIRECTORY_SEPARATOR !== '\\') {
            $perms = @fileperms($envPath);
            if ($perms !== false && (($perms & 0002) !== 0)) {
                $checks[] = new CheckResult('.env permissions', 'fail', '.env is world-writable.', 'Restrict write permissions so untrusted local users cannot modify environment secrets.');
            } elseif ($perms !== false && (($perms & 0020) !== 0)) {
                $checks[] = new CheckResult('.env permissions', 'warning', '.env is group-writable.', 'Review group ownership and remove write access unless explicitly required.');
            } elseif ($perms === false) {
                $checks[] = new CheckResult('.env permissions', 'warning', 'Could not read .env file permissions.', 'Verify the file ownership and permissions using the host operating system.');
            } else {
                $checks[] = new CheckResult('.env permissions', 'pass', '.env is not group- or world-writable.');
            }
        }

        $checks[] = $this->databaseCheck($env, $root);
        $checks[] = $this->redisCheck($env);

        return $this->report($checks, $root);
    }

    /** @param array<string,string> $env */
    private function databaseCheck(array $env, string $root): CheckResult
    {
        if (!$this->networkChecks) {
            return new CheckResult('Database connectivity', 'skip', 'Database connectivity was not checked; pass --network to opt in.');
        }

        $driver = strtolower(trim($env['DB_CONNECTION'] ?? ''));
        if ($driver === '') {
            return new CheckResult('Database connectivity', 'skip', 'DB_CONNECTION is not available from .env or the process environment.');
        }

        try {
            if ($driver === 'sqlite') {
                $database = trim($env['DB_DATABASE'] ?? ($root . '/database/database.sqlite'));
                if ($database === '') {
                    return new CheckResult('Database connectivity', 'warning', 'DB_DATABASE is empty.', 'Set DB_DATABASE to the intended SQLite file.');
                }
                if ($database !== ':memory:' && !self::isAbsolutePath($database)) {
                    $database = $root . '/' . $database;
                }
                if ($database !== ':memory:' && !is_file($database)) {
                    return new CheckResult('Database connectivity', 'warning', 'The configured SQLite database file was not found.', 'Verify DB_DATABASE points to the intended SQLite file.');
                }
                // Use read-only mode where the PDO SQLite driver exposes it, so a check never creates a file.
                $options = [PDO::ATTR_TIMEOUT => 2, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
                if (defined('PDO::SQLITE_ATTR_OPEN_FLAGS') && defined('PDO::SQLITE_OPEN_READONLY') && $database !== ':memory:') {
                    $options[constant('PDO::SQLITE_ATTR_OPEN_FLAGS')] = constant('PDO::SQLITE_OPEN_READONLY');
                }
                new PDO('sqlite:' . $database, null, null, $options);
                return new CheckResult('Database connectivity', 'pass', 'SQLite database opened successfully.');
            }

            $map = ['mysql' => 'mysql', 'mariadb' => 'mysql', 'pgsql' => 'pgsql'];
            if (!isset($map[$driver])) {
                return new CheckResult('Database connectivity', 'skip', 'The configured database driver is not supported by this connectivity check.', 'Use mysql, mariadb, pgsql, or sqlite for the built-in check.');
            }

            $host = trim($env['DB_HOST'] ?? '127.0.0.1');
            $port = trim($env['DB_PORT'] ?? ($driver === 'pgsql' ? '5432' : '3306'));
            $database = $env['DB_DATABASE'] ?? '';
            if ($database === '') {
                return new CheckResult('Database connectivity', 'warning', 'DB_DATABASE is empty.', 'Set the database name in the deployment environment.');
            }

            // Reject DSN delimiters/control characters before constructing a PDO DSN.
            if ($host === '' || !preg_match('/^[A-Za-z0-9._:\-\[\]]+$/', $host)
                || !preg_match('/^[0-9]{1,5}$/', $port) || (int) $port < 1 || (int) $port > 65535
                || preg_match('/[;\x00\r\n]/', $database)) {
                return new CheckResult('Database connectivity', 'warning', 'Database connection settings contain unsupported characters.', 'Review DB_HOST, DB_PORT, and DB_DATABASE; no connection was attempted.');
            }

            $dsn = $map[$driver] . ':host=' . $host . ';port=' . $port . ';dbname=' . $database;
            if ($driver !== 'pgsql' && isset($env['DB_CHARSET'])) {
                $charset = preg_replace('/[^A-Za-z0-9_]/', '', $env['DB_CHARSET']) ?? '';
                if ($charset !== '') {
                    $dsn .= ';charset=' . $charset;
                }
            }
            if ($driver === 'pgsql') {
                $dsn .= ';connect_timeout=2';
            }

            $options = [PDO::ATTR_TIMEOUT => 2, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
            new PDO($dsn, $env['DB_USERNAME'] ?? '', $env['DB_PASSWORD'] ?? '', $options);
            return new CheckResult('Database connectivity', 'pass', 'Database connection succeeded; credentials are not displayed.');
        } catch (Throwable) {
            return new CheckResult('Database connectivity', 'warning', 'Database connection failed; details are suppressed to protect configuration secrets.', 'Verify the driver extension, host, port, database name, credentials, firewall rules, and database availability.');
        }
    }

    /** @param array<string,string> $env */
    private function redisCheck(array $env): CheckResult
    {
        if (!$this->networkChecks) {
            return new CheckResult('Redis connectivity', 'skip', 'Redis connectivity was not checked; pass --network to opt in.');
        }

        $host = trim($env['REDIS_HOST'] ?? '');
        if ($host === '') {
            return new CheckResult('Redis connectivity', 'skip', 'REDIS_HOST is not available from .env or the process environment.');
        }

        if (!self::isSafeNetworkHost($host)) {
            return new CheckResult('Redis connectivity', 'warning', 'REDIS_HOST is invalid for a TCP connectivity check; no connection was attempted.', 'Use a hostname or IP address only. URI schemes, paths, and other socket syntax are not supported.');
        }

        $portString = trim($env['REDIS_PORT'] ?? '6379');
        if (!preg_match('/^[0-9]{1,5}$/', $portString)) {
            return new CheckResult('Redis connectivity', 'warning', 'REDIS_PORT is invalid.', 'Set REDIS_PORT to a valid TCP port.');
        }
        $port = (int) $portString;
        if ($port < 1 || $port > 65535) {
            return new CheckResult('Redis connectivity', 'warning', 'REDIS_PORT is invalid.', 'Set REDIS_PORT to a valid TCP port.');
        }

        // Network access happens only after explicit --network opt-in.
        $errno = 0;
        $error = '';
        $socketHost = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false
            ? '[' . $host . ']'
            : $host;
        $socket = @fsockopen($socketHost, $port, $errno, $error, 1.5);
        if (is_resource($socket)) {
            fclose($socket);
            return new CheckResult('Redis connectivity', 'pass', 'Redis TCP endpoint is reachable; authentication, TLS, and command execution were not tested.');
        }

        return new CheckResult('Redis connectivity', 'warning', 'Redis TCP endpoint could not be reached; connection details are suppressed.', 'Verify Redis host/port, service health, firewall rules, TLS requirements, and network access.');
    }

    private static function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }

    /** Accept IP literals and conventional DNS hostnames; reject stream-wrapper syntax. */
    private static function isSafeNetworkHost(string $host): bool
    {
        if ($host === '' || preg_match('~[\x00-\x20\x7f\\\\/@?#]~', $host) === 1 || str_contains($host, '://')) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        $hostname = rtrim($host, '.');
        if ($hostname === '' || strlen($hostname) > 253 || preg_match('/^[A-Za-z0-9.-]+$/', $hostname) !== 1) {
            return false;
        }

        foreach (explode('.', $hostname) as $label) {
            if ($label === '' || strlen($label) > 63 || preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?$/', $label) !== 1) {
                return false;
            }
        }

        return true;
    }

    /** @param list<CheckResult> $checks */
    private function report(array $checks, ?string $root = null): array
    {
        $summary = ['pass' => 0, 'warning' => 0, 'fail' => 0, 'skip' => 0];
        foreach ($checks as $check) {
            $summary[$check->status]++;
        }

        $path = $root ?? rtrim($this->projectPath, DIRECTORY_SEPARATOR);
        return [
            'project' => basename($path === '' ? DIRECTORY_SEPARATOR : $path),
            'generated_at' => gmdate(DATE_ATOM),
            'checks' => array_map(static fn (CheckResult $check) => $check->toArray(), $checks),
            'summary' => $summary,
        ];
    }
}
