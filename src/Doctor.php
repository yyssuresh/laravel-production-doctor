<?php

declare(strict_types=1);

namespace LaravelProductionDoctor;

use LaravelProductionDoctor\Support\EnvFile;
use PDO;
use Throwable;

final class Doctor
{
    /** @param string $projectPath Absolute or relative Laravel project directory */
    public function __construct(private readonly string $projectPath, private readonly bool $networkChecks = true) {}

    /** @return array{project:string,generated_at:string,checks:list<array{name:string,status:string,message:string,recommendation:?string}>,summary:array{pass:int,warning:int,fail:int,skip:int}} */
    public function run(): array
    {
        $checks = [];
        $isLaravel = is_file($this->projectPath . '/artisan') && is_file($this->projectPath . '/composer.json');
        $checks[] = new CheckResult('Laravel project', $isLaravel ? 'pass' : 'fail', $isLaravel ? 'Laravel project markers were found.' : 'Could not find both artisan and composer.json.', $isLaravel ? null : 'Run this command from a Laravel application directory or use --path.');
        if (!$isLaravel) return $this->report($checks);

        $envPath = $this->projectPath . '/.env';
        $envExists = is_file($envPath);
        $env = EnvFile::fromFile($envPath);
        $checks[] = new CheckResult('.env file', $envExists ? 'pass' : 'warning', $envExists ? 'An environment file exists.' : 'No .env file was found; environment may be injected externally.', $envExists ? null : 'Confirm production environment variables are supplied by your deployment platform.');

        $debug = strtolower($env['APP_DEBUG'] ?? '');
        if ($debug === 'true' || $debug === '1' || $debug === 'yes') {
            $checks[] = new CheckResult('Debug mode', 'fail', 'APP_DEBUG appears to be enabled.', 'Set APP_DEBUG=false in production and clear/rebuild the configuration cache during your normal deployment process.');
        } elseif ($debug === 'false' || $debug === '0' || $debug === 'no') {
            $checks[] = new CheckResult('Debug mode', 'pass', 'APP_DEBUG is disabled.');
        } else {
            $checks[] = new CheckResult('Debug mode', 'warning', 'APP_DEBUG is missing or could not be interpreted.', 'Set APP_DEBUG=false explicitly in production.');
        }

        $appEnv = strtolower($env['APP_ENV'] ?? '');
        $checks[] = match ($appEnv) {
            'production', 'prod' => new CheckResult('Application environment', 'pass', 'APP_ENV is set to production.'),
            '' => new CheckResult('Application environment', 'warning', 'APP_ENV is not set in the readable .env file.', 'Set APP_ENV=production, or confirm the deployment platform injects it.'),
            default => new CheckResult('Application environment', 'warning', 'APP_ENV is set to a non-production value.', 'Confirm this is intentional for the environment being checked.'),
        };

        $key = trim($env['APP_KEY'] ?? '');
        $checks[] = new CheckResult('Application key', $key !== '' ? 'pass' : 'fail', $key !== '' ? 'APP_KEY is present (value intentionally hidden).' : 'APP_KEY is missing from the readable .env file.', $key !== '' ? null : 'Generate and configure an application key through your normal secure deployment process; never share it in logs.');

        $phpOk = PHP_VERSION_ID >= 80200;
        $checks[] = new CheckResult('PHP version', $phpOk ? 'pass' : 'fail', 'CLI PHP version is ' . PHP_VERSION . '.', $phpOk ? null : 'Use PHP 8.2 or newer for this tool and verify the version required by your Laravel release.');
        foreach (['ctype', 'filter', 'mbstring', 'openssl', 'pdo', 'tokenizer', 'xml'] as $extension) {
            $loaded = extension_loaded($extension);
            $checks[] = new CheckResult('PHP extension: ' . $extension, $loaded ? 'pass' : 'warning', $loaded ? 'Extension is loaded.' : 'Extension is not loaded.', $loaded ? null : 'Install/enable this extension if it is required by your Laravel application or dependencies.');
        }

        $configCache = $this->projectPath . '/bootstrap/cache/config.php';
        $checks[] = new CheckResult('Configuration cache', is_file($configCache) ? 'pass' : 'warning', is_file($configCache) ? 'A configuration cache file exists.' : 'No configuration cache file was found.', is_file($configCache) ? 'Confirm your deployment process refreshes cached configuration after environment changes.' : 'For production, consider caching configuration as part of your deployment process.');

        if ($envExists && DIRECTORY_SEPARATOR !== '\\') {
            $perms = @fileperms($envPath);
            if ($perms !== false && (($perms & 0002) !== 0)) {
                $checks[] = new CheckResult('.env permissions', 'fail', '.env is world-writable.', 'Restrict write permissions so untrusted local users cannot modify environment secrets.');
            } elseif ($perms !== false && (($perms & 0020) !== 0)) {
                $checks[] = new CheckResult('.env permissions', 'warning', '.env is group-writable.', 'Review group ownership and remove write access unless explicitly required.');
            } else {
                $checks[] = new CheckResult('.env permissions', 'pass', '.env is not group- or world-writable.');
            }
        }

        $checks[] = $this->databaseCheck($env);
        $checks[] = $this->redisCheck($env);
        return $this->report($checks);
    }

    /** @param array<string,string> $env */
    private function databaseCheck(array $env): CheckResult
    {
        $driver = strtolower($env['DB_CONNECTION'] ?? '');
        if ($driver === '') return new CheckResult('Database connectivity', 'skip', 'DB_CONNECTION is not set in the readable .env file.', 'Set DB_CONNECTION or confirm database configuration is injected externally.');
        if (!$this->networkChecks) return new CheckResult('Database connectivity', 'skip', 'Network checks were disabled by --no-network.');
        try {
            if ($driver === 'sqlite') {
                $database = $env['DB_DATABASE'] ?? ($this->projectPath . '/database/database.sqlite');
                if ($database !== ':memory:' && !str_starts_with($database, '/')) $database = $this->projectPath . '/' . $database;
                if ($database !== ':memory:' && !is_file($database)) return new CheckResult('Database connectivity', 'warning', 'The configured SQLite database file was not found.', 'Verify DB_DATABASE points to the intended SQLite file.');
                new PDO('sqlite:' . $database, null, null, [PDO::ATTR_TIMEOUT => 2]);
                return new CheckResult('Database connectivity', 'pass', 'SQLite database opened successfully.');
            }
            $map = ['mysql' => 'mysql', 'mariadb' => 'mysql', 'pgsql' => 'pgsql'];
            if (!isset($map[$driver])) return new CheckResult('Database connectivity', 'skip', 'The configured database driver is not supported by this connectivity check.', 'Use mysql, mariadb, pgsql, or sqlite for the built-in check.');
            $host = $env['DB_HOST'] ?? '127.0.0.1';
            $port = $env['DB_PORT'] ?? ($driver === 'pgsql' ? '5432' : '3306');
            $database = $env['DB_DATABASE'] ?? '';
            if ($database === '') return new CheckResult('Database connectivity', 'warning', 'DB_DATABASE is empty.', 'Set the database name in the deployment environment.');
            // Reject DSN delimiters so values from .env cannot inject extra PDO options.
            if (!preg_match('/^[A-Za-z0-9._:\-]+$/', $host) || !preg_match('/^[0-9]{1,5}$/', $port) || (int) $port < 1 || (int) $port > 65535 || preg_match('/[;\x00\r\n]/', $database)) {
                return new CheckResult('Database connectivity', 'warning', 'Database connection settings contain unsupported characters.', 'Review DB_HOST, DB_PORT, and DB_DATABASE; no connection was attempted.');
            }
            $dsn = $map[$driver] . ':host=' . $host . ';port=' . $port . ';dbname=' . $database;
            if ($driver !== 'pgsql' && isset($env['DB_CHARSET'])) $dsn .= ';charset=' . preg_replace('/[^A-Za-z0-9_]/', '', $env['DB_CHARSET']);
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
        $host = trim($env['REDIS_HOST'] ?? '');
        if ($host === '') return new CheckResult('Redis connectivity', 'skip', 'REDIS_HOST is not set in the readable .env file.');
        if (!$this->networkChecks) return new CheckResult('Redis connectivity', 'skip', 'Network checks were disabled by --no-network.');
        $port = (int) ($env['REDIS_PORT'] ?? '6379');
        if ($port < 1 || $port > 65535) return new CheckResult('Redis connectivity', 'warning', 'REDIS_PORT is invalid.', 'Set REDIS_PORT to a valid TCP port.');
        $errno = 0; $error = '';
        $socket = @fsockopen($host, $port, $errno, $error, 1.5);
        if (is_resource($socket)) { fclose($socket); return new CheckResult('Redis connectivity', 'pass', 'Redis TCP endpoint is reachable; authentication and command execution were not tested.'); }
        return new CheckResult('Redis connectivity', 'warning', 'Redis TCP endpoint could not be reached; connection details are suppressed.', 'Verify Redis host/port, service health, firewall rules, TLS requirements, and network access.');
    }

    /** @param list<CheckResult> $checks */
    private function report(array $checks): array
    {
        $summary = ['pass' => 0, 'warning' => 0, 'fail' => 0, 'skip' => 0];
        foreach ($checks as $check) $summary[$check->status]++;
        return ['project' => basename($this->projectPath), 'generated_at' => gmdate(DATE_ATOM), 'checks' => array_map(static fn (CheckResult $c) => $c->toArray(), $checks), 'summary' => $summary];
    }
}
