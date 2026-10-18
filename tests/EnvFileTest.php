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

    public function testParsesQuotedValuesWithoutEvaluatingExpressions(): void
    {
        $values = EnvFile::parse("APP_NAME=\"Production App\"\nAPP_KEY='base64:abc== '\nDANGEROUS=$(touch /tmp/not-run)\n");
        self::assertSame('Production App', $values['APP_NAME']);
        self::assertSame('base64:abc== ', $values['APP_KEY']);
        self::assertSame('$(touch /tmp/not-run)', $values['DANGEROUS']);
    }
}
