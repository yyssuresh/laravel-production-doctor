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
        foreach ($files as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($this->directory);
    }

    public function testDetectsProductionDebugMisconfiguration(): void
    {
        file_put_contents($this->directory . '/.env', "APP_ENV=production\nAPP_DEBUG=true\nAPP_KEY=example-secret-do-not-print\nDB_CONNECTION=unknown\n");
        $report = (new Doctor($this->directory, false))->run();
        $debug = array_values(array_filter($report['checks'], fn ($c) => $c['name'] === 'Debug mode'))[0];
        self::assertSame('fail', $debug['status']);
        self::assertStringNotContainsString('example-secret-do-not-print', json_encode($report));
    }

    public function testReportsMissingLaravelMarkers(): void
    {
        unlink($this->directory . '/artisan');
        $report = (new Doctor($this->directory, false))->run();
        self::assertSame('fail', $report['checks'][0]['status']);
        self::assertSame(1, $report['summary']['fail']);
    }
}
