<?php

declare(strict_types=1);

namespace LaravelProductionDoctor\Tests;

use LaravelProductionDoctor\Doctor;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;

final class JsonReportSchemaTest extends TestCase
{
    public function testReportMatchesDocumentedJsonSchemaStructure(): void
    {
        $fixture = __DIR__ . '/Fixtures/LaravelApp';
        $schemaPath = dirname(__DIR__) . '/docs/json-report-schema.json';

        self::assertFileExists($schemaPath);

        $schemaContent = file_get_contents($schemaPath);
        self::assertNotFalse($schemaContent);

        $schema = json_decode(
            $schemaContent,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertSame('object', $schema['type']);

        self::assertSame(
            ['project', 'generated_at', 'checks', 'summary'],
            $schema['required']
        );

        $report = (new Doctor($fixture))->run();

        // Validate the complete report against the actual JSON Schema.
        $schemaObject = json_decode(
            $schemaContent,
            false,
            512,
            JSON_THROW_ON_ERROR
        );

        $reportObject = json_decode(
            json_encode($report, JSON_THROW_ON_ERROR),
            false,
            512,
            JSON_THROW_ON_ERROR
        );

        $validator = new Validator();
        $result = $validator->validate($reportObject, $schemaObject);

        self::assertTrue(
            $result->isValid(),
            'Generated report must match JSON Schema: '
                . ($result->error()?->message() ?? 'Unknown validation error')
        );

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
            self::assertGreaterThanOrEqual(
                0,
                $report['summary'][$status]
            );
            self::assertSame($count, $report['summary'][$status]);
        }
    }

    public function testJsonSchemaRejectsInvalidReport(): void
    {
        $schemaPath = dirname(__DIR__) . '/docs/json-report-schema.json';

        $schemaContent = file_get_contents($schemaPath);
        self::assertNotFalse($schemaContent);

        $schema = json_decode(
            $schemaContent,
            false,
            512,
            JSON_THROW_ON_ERROR
        );

        $invalidReport = (object) [
            'project' => '',
            'generated_at' => 'not-a-date',
            'checks' => [],
            'summary' => [
                'pass' => -1,
                'warning' => 0,
                'fail' => 0,
                'skip' => 0,
            ],
            'unexpected_field' => true,
        ];

        $validator = new Validator();
        $result = $validator->validate($invalidReport, $schema);

        self::assertFalse(
            $result->isValid(),
            'The schema must reject an invalid report.'
        );
    }
}
