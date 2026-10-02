<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Tests\Cli;

use Kontirol\ApiDoc\Cli\Arguments;
use PHPUnit\Framework\TestCase;

final class ArgumentsTest extends TestCase
{
    public function testSeparatesPositionalArguments(): void
    {
        $arguments = Arguments::parse(['generate', 'extra']);

        self::assertSame(['generate', 'extra'], $arguments->arguments());
        self::assertSame('generate', $arguments->command());
    }

    public function testParsesLongOptionWithValue(): void
    {
        $arguments = Arguments::parse(['generate', '--config', 'apidoc.php'], ['config']);

        self::assertSame('apidoc.php', $arguments->option('config'));
        self::assertSame('generate', $arguments->command());
    }

    public function testParsesLongOptionWithEquals(): void
    {
        $arguments = Arguments::parse(['generate', '--config=apidoc.php']);

        self::assertSame('apidoc.php', $arguments->option('config'));
    }

    public function testParsesShortOptionWithValue(): void
    {
        $arguments = Arguments::parse(['generate', '-c', 'apidoc.php'], ['c']);

        self::assertSame('apidoc.php', $arguments->option('c'));
    }

    public function testFlagsCarryNoValue(): void
    {
        $arguments = Arguments::parse(['generate', '--no-reflection', '-v']);

        self::assertTrue($arguments->flag('no-reflection'));
        self::assertTrue($arguments->flag('v'));
        self::assertNull($arguments->option('no-reflection'));
    }

    public function testUnknownOptionIsReportedAsAbsent(): void
    {
        $arguments = Arguments::parse(['generate']);

        self::assertFalse($arguments->flag('strict'));
        self::assertNull($arguments->option('strict'));
    }

    public function testDoubleDashStopsOptionParsing(): void
    {
        $arguments = Arguments::parse(['generate', '--', '--not-an-option']);

        self::assertSame(['generate', '--not-an-option'], $arguments->arguments());
        self::assertFalse($arguments->flag('not-an-option'));
    }

    public function testCommandIsNullWhenOnlyOptionsAreGiven(): void
    {
        $arguments = Arguments::parse(['--help']);

        self::assertNull($arguments->command());
        self::assertTrue($arguments->flag('help'));
    }
}
