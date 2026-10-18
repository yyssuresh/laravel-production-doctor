<?php

declare(strict_types=1);

namespace LaravelProductionDoctor\Tests;

use LaravelProductionDoctor\Support\EnvFile;
use PHPUnit\Framework\TestCase;

final class EnvFileTest extends TestCase
{
    public function testParsesSimpleValuesAndIgnoresComments(): void
    {
        $values = EnvFile::parse("# comment\nAPP_ENV=production\nAPP_DEBUG=false\nDB_HOST=localhost # inline comment\n");
        self::assertSame('production', $values['APP_ENV']);
        self::assertSame('false', $values['APP_DEBUG']);
        self::assertSame('localhost', $values['DB_HOST']);
    }

    public function testParsesQuotedValuesAndKeepsHashInsideQuotes(): void
    {
        $values = EnvFile::parse("APP_NAME=\"Production # App\"\nAPP_KEY='base64:abc== '\nPASSWORD=plain#hash\n");
        self::assertSame('Production # App', $values['APP_NAME']);
        self::assertSame('base64:abc== ', $values['APP_KEY']);
        self::assertSame('plain#hash', $values['PASSWORD']);
    }

    public function testDecodesDoubleQuotedEscapesAndMultilineValues(): void
    {
        $values = EnvFile::parse("MESSAGE=\"line one\\nline two\"\nCERT='first line\nsecond line'\n");
        self::assertSame("line one\nline two", $values['MESSAGE']);
        self::assertSame("first line\nsecond line", $values['CERT']);
    }

    public function testIgnoresMalformedQuotedValues(): void
    {
        $values = EnvFile::parse("APP_ENV=production\nBROKEN=\"never closes");
        self::assertSame('production', $values['APP_ENV']);
        self::assertArrayNotHasKey('BROKEN', $values);
    }

    public function testDoesNotEvaluateShellExpressions(): void
    {
        $values = EnvFile::parse("DANGEROUS=$(touch /tmp/not-run)\nexport APP_ENV=production\n");
        self::assertSame('$(touch /tmp/not-run)', $values['DANGEROUS']);
        self::assertSame('production', $values['APP_ENV']);
    }

    public function testProcessEnvironmentOverridesFileValues(): void
    {
        $values = EnvFile::withProcessEnvironment(
            ['APP_ENV' => 'local', 'APP_DEBUG' => 'true', 'APP_KEY' => 'file-secret'],
            ['APP_ENV' => 'server'],
            ['APP_ENV' => 'env-array'],
            static fn (string $key): string|false => $key === 'APP_ENV' ? 'injected-production' : false,
        );

        self::assertSame('injected-production', $values['APP_ENV']);
        self::assertSame('true', $values['APP_DEBUG']);
        self::assertSame('file-secret', $values['APP_KEY']);
    }


    public function testUnterminatedQuoteDoesNotConsumeFollowingAssignments(): void
    {
        $values = EnvFile::parse("BROKEN=\"never closes\nAPP_ENV=production\nAPP_DEBUG=false\n");
        self::assertArrayNotHasKey('BROKEN', $values);
        self::assertSame('production', $values['APP_ENV']);
        self::assertSame('false', $values['APP_DEBUG']);
    }

    public function testRejectsUnexpectedSuffixAfterQuotedValue(): void
    {
        $values = EnvFile::parse("APP_ENV=\"production\"junk\nAPP_DEBUG=false\n");
        self::assertArrayNotHasKey('APP_ENV', $values);
        self::assertSame('false', $values['APP_DEBUG']);
    }

    public function testCustomEnvironmentLookupDoesNotEnumerateRealProcessEnvironment(): void
    {
        $values = EnvFile::withProcessEnvironment(
            ['APP_ENV' => 'file-value'],
            [],
            [],
            static fn (string $key): string|false => false,
        );
        self::assertSame(['APP_ENV' => 'file-value'], $values);
    }


    public function testExplicitProcessEnvironmentSnapshotTakesPrecedence(): void
    {
        $values = EnvFile::withProcessEnvironment(
            ['APP_ENV' => 'file-value'],
            [],
            [],
            static fn (string $key): string|false => false,
            ['APP_ENV' => 'snapshot-production'],
        );
        self::assertSame('snapshot-production', $values['APP_ENV']);
    }

    public function testFileValuesAreUsedWhenNoProcessValueExists(): void
    {
        $values = EnvFile::withProcessEnvironment(
            ['APP_ENV' => 'production'],
            [],
            [],
            static fn (string $key): string|false => false,
        );
        self::assertSame('production', $values['APP_ENV']);
    }
}
