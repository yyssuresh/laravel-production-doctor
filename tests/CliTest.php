<?php

declare(strict_types=1);

namespace LaravelProductionDoctor\Tests;

use PHPUnit\Framework\TestCase;

final class CliTest extends TestCase
{
    private string $projectRoot;
    private string $fixture;

    protected function setUp(): void
    {
        $this->projectRoot = dirname(__DIR__);
        $this->fixture = sys_get_temp_dir() . '/lpd-cli-' . bin2hex(random_bytes(5));
        mkdir($this->fixture, 0700, true);
        mkdir($this->fixture . '/bootstrap/cache', 0700, true);
        file_put_contents($this->fixture . '/artisan', "<?php // fixture\n");
        file_put_contents($this->fixture . '/composer.json', '{}');
        file_put_contents($this->fixture . '/.env', "APP_ENV=production\nAPP_DEBUG=false\nAPP_KEY=cli-test-secret\nDB_CONNECTION=unknown\n");
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->fixture)) {
            return;
        }
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->fixture, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->fixture);
    }

    public function testHelpPrintsUsageAndExitsSuccessfully(): void
    {
        [$code, $stdout, $stderr] = $this->runCli(['--help']);
        self::assertSame(0, $code);
        self::assertStringContainsString('Usage: laravel-doctor', $stdout);
        self::assertStringContainsString('--network', $stdout);
        self::assertSame('', $stderr);
    }

    public function testJsonOptionProducesValidJsonAndSkipsNetworkByDefault(): void
    {
        [$code, $stdout, $stderr] = $this->runCli(['--path=' . $this->fixture, '--json']);
        self::assertSame(0, $code);
        self::assertSame('', $stderr);
        $report = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('skip', $this->findCheck($report, 'Database connectivity')['status']);
        self::assertSame('skip', $this->findCheck($report, 'Redis connectivity')['status']);
        self::assertStringNotContainsString('cli-test-secret', $stdout);
    }

    public function testNetworkFlagOptsInAndIsReportedAsUsed(): void
    {
        // Unknown DB driver and no Redis host ensure this test does not make a network request.
        [$code, $stdout] = $this->runCli(['--path', $this->fixture, '--network', '--json']);
        self::assertSame(0, $code);
        $report = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('skip', $this->findCheck($report, 'Database connectivity')['status']);
    }

    public function testInvalidPathExitsWithCodeTwo(): void
    {
        [$code, $stdout, $stderr] = $this->runCli(['--path=' . $this->fixture . '/does-not-exist']);
        self::assertSame(2, $code);
        self::assertSame('', $stdout);
        self::assertStringContainsString('Project path is not a directory.', $stderr);
    }

    public function testUnknownArgumentExitsWithCodeTwo(): void
    {
        [$code, $stdout, $stderr] = $this->runCli(['--netwrok']);
        self::assertSame(2, $code);
        self::assertSame('', $stdout);
        self::assertStringContainsString('Unknown argument: --netwrok', $stderr);
    }

    public function testDuplicateOptionsAreRejected(): void
    {
        [$code, , $stderr] = $this->runCli(['--json', '--json']);
        self::assertSame(2, $code);
        self::assertStringContainsString('may only be supplied once', $stderr);
    }

    public function testFailedDiagnosticsExitWithCodeOne(): void
    {
        $empty = $this->fixture . '/not-laravel';
        mkdir($empty);
        [$code, $stdout] = $this->runCli(['--path=' . $empty, '--json']);
        self::assertSame(1, $code);
        $report = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(1, $report['summary']['fail']);
    }

    /** @param list<string> $arguments @return array{int,string,string} */
    private function runCli(array $arguments): array
    {
        $command = array_merge([PHP_BINARY, $this->projectRoot . '/bin/laravel-doctor'], $arguments);
        $escaped = implode(' ', array_map('escapeshellarg', $command));
        $process = proc_open($escaped, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->projectRoot);
        self::assertIsResource($process, 'Unable to start CLI process.');
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $code = proc_close($process);
        return [$code, $stdout, $stderr];
    }

    /** @param array<string,mixed> $report @return array<string,mixed> */
    private function findCheck(array $report, string $name): array
    {
        foreach ($report['checks'] as $check) {
            if ($check['name'] === $name) {
                return $check;
            }
        }
        self::fail('Missing check: ' . $name);
    }
}
