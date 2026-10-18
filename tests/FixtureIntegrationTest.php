<?php

declare(strict_types=1);

namespace LaravelProductionDoctor\Tests;

use LaravelProductionDoctor\Doctor;
use PHPUnit\Framework\TestCase;

final class FixtureIntegrationTest extends TestCase
{
    public function testProductionFixtureProducesExpectedDiagnosticReport(): void
    {
        $fixture = __DIR__ . '/Fixtures/LaravelApp';

        self::assertFileExists($fixture . '/artisan');
        self::assertFileExists($fixture . '/composer.json');
        self::assertFileExists($fixture . '/.env');

        $report = (new Doctor($fixture))->run();

        self::assertSame('LaravelApp', $report['project']);
        self::assertArrayHasKey('generated_at', $report);
        self::assertIsArray($report['checks']);
        self::assertSame(
            ['pass', 'warning', 'fail', 'skip'],
            array_keys($report['summary'])
        );

        $checks = [];
        foreach ($report['checks'] as $check) {
            $checks[$check['name']] = $check;
        }

        self::assertSame('pass', $checks['Laravel project']['status']);
        self::assertSame('pass', $checks['.env file']['status']);
        self::assertSame('pass', $checks['Debug mode']['status']);
        self::assertSame(
            'pass',
            $checks['Application environment']['status']
        );
        self::assertSame('pass', $checks['Application key']['status']);

        self::assertSame(
            'skip',
            $checks['Database connectivity']['status']
        );
        self::assertSame(
            'skip',
            $checks['Redis connectivity']['status']
        );

        $reportJson = json_encode($report, JSON_THROW_ON_ERROR);

        self::assertStringNotContainsString(
            'fixture-test-key-not-a-real-secret',
            $reportJson
        );

        $summary = ['pass' => 0, 'warning' => 0, 'fail' => 0, 'skip' => 0];

        foreach ($report['checks'] as $check) {
            self::assertArrayHasKey('name', $check);
            self::assertArrayHasKey('status', $check);
            self::assertArrayHasKey('message', $check);
            self::assertArrayHasKey('recommendation', $check);

            self::assertContains(
                $check['status'],
                ['pass', 'warning', 'fail', 'skip']
            );

            $summary[$check['status']]++;
        }

        self::assertSame($summary, $report['summary']);
    }
}
