<?php

declare(strict_types=1);

namespace LaravelProductionDoctor\Tests;

use LaravelProductionDoctor\Doctor;
use PHPUnit\Framework\TestCase;

final class DoctorTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/lpd-' . bin2hex(random_bytes(5));
        mkdir($this->directory, 0700, true);
        mkdir($this->directory . '/bootstrap/cache', 0700, true);
        file_put_contents($this->directory . '/artisan', "<?php // fixture\n");
        file_put_contents($this->directory . '/composer.json', '{}');
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->directory);
    }

    public function testDetectsProductionDebugMisconfigurationWithoutExposingSecrets(): void
    {
        file_put_contents($this->directory . '/.env', "APP_ENV=production\nAPP_DEBUG=true\nAPP_KEY=example-secret-do-not-print\nDB_CONNECTION=unknown\n");
        $report = (new Doctor($this->directory))->run();
        $debug = $this->check($report, 'Debug mode');
        self::assertSame('fail', $debug['status']);
        self::assertStringNotContainsString('example-secret-do-not-print', json_encode($report));
    }

    public function testReportsMissingLaravelMarkers(): void
    {
        unlink($this->directory . '/artisan');
        $report = (new Doctor($this->directory))->run();
        self::assertSame('fail', $report['checks'][0]['status']);
        self::assertSame(1, $report['summary']['fail']);
    }

    public function testNetworkChecksAreSkippedByDefault(): void
    {
        file_put_contents($this->directory . '/.env', "APP_ENV=production\nAPP_DEBUG=false\nAPP_KEY=example-secret\nDB_CONNECTION=mysql\nDB_HOST=192.0.2.1\nDB_DATABASE=example\nREDIS_HOST=192.0.2.2\n");
        $report = (new Doctor($this->directory))->run();
        self::assertStringContainsString('pass --network to opt in', $this->check($report, 'Database connectivity')['message']);
        self::assertStringContainsString('pass --network to opt in', $this->check($report, 'Redis connectivity')['message']);
        self::assertStringNotContainsString('example-secret', json_encode($report));
    }

    public function testUsesInjectedEnvironmentValuesBeforeDotEnvValues(): void
    {
        file_put_contents($this->directory . '/.env', "APP_ENV=local\nAPP_DEBUG=true\nAPP_KEY=file-secret\nDB_CONNECTION=unknown\n");
        $_SERVER['APP_ENV'] = 'production';
        $_SERVER['APP_DEBUG'] = 'false';
        $_SERVER['APP_KEY'] = 'injected-secret';
        try {
            $report = (new Doctor($this->directory))->run();
            self::assertSame('pass', $this->check($report, 'Debug mode')['status']);
            self::assertSame('pass', $this->check($report, 'Application environment')['status']);
            self::assertStringNotContainsString('injected-secret', json_encode($report));
            self::assertStringNotContainsString('file-secret', json_encode($report));
        } finally {
            unset($_SERVER['APP_ENV'], $_SERVER['APP_DEBUG'], $_SERVER['APP_KEY']);
        }
    }

    public function testJsonReportHasStableSummaryKeys(): void
    {
        $report = (new Doctor($this->directory))->run();
        self::assertSame(['pass', 'warning', 'fail', 'skip'], array_keys($report['summary']));
        self::assertIsString(json_encode($report));
    }

    /** @param array<string,mixed> $report @return array{name:string,status:string,message:string,recommendation:?string} */
    private function check(array $report, string $name): array
    {
        foreach ($report['checks'] as $check) {
            if ($check['name'] === $name) {
                return $check;
            }
        }
        self::fail('Missing check: ' . $name);
    }
}
