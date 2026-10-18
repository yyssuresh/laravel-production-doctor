<?php

declare(strict_types=1);

namespace LaravelProductionDoctor\Tests;

use LaravelProductionDoctor\Doctor;
use PHPUnit\Framework\TestCase;

final class JsonReportSchemaTest extends TestCase
{
    public function testReportMatchesDocumentedJsonSchemaStructure(): void
    {
        $fixture = __DIR__ . '/Fixtures/LaravelApp';
        $schemaPath = dirname(__DIR__) . '/docs/json-report-schema.json';

        self::assertFileExists($schemaPath);

        $schema = json_decode(
            file_get_contents($schemaPath),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertSame(
            'object',
            $schema['type']
        );

        self::assertSame(
            ['project', 'generated_at', 'checks', 'summary'],
            $schema['required']
        );

        $report = (new Doctor($fixture))->run();

        self::assertIsString($report['project']);
        self::assertNotSame('', $report['project']);

        $timestamp = \DateTimeImmutable::createFromFormat(
            DATE_ATOM,
            $report['generated_at']
        );

        self::assertNotFalse($timestamp);

        self::assertIsArray($report['checks']);

        $summary = [
            'pass' => 0,
            'warning' => 0,
            'fail' => 0,
            'skip' => 0,
        ];

        foreach ($report['checks'] as $check) {
            self::assertSame(
                ['name', 'status', 'message', 'recommendation'],
                array_keys($check)
            );

            self::assertIsString($check['name']);
            self::assertNotSame('', $check['name']);
            self::assertContains(
                $check['status'],
                ['pass', 'warning', 'fail', 'skip']
            );
            self::assertIsString($check['message']);

            self::assertTrue(
                $check['recommendation'] === null
                || is_string($check['recommendation'])
            );

            $summary[$check['status']]++;
        }

        self::assertSame($summary, $report['summary']);

        foreach ($summary as $status => $count) {
            self::assertIsInt($report['summary'][$status]);
            self::assertGreaterThanOrEqual(0, $report['summary'][$status]);
            self::assertSame($count, $report['summary'][$status]);
        }
    }
}
