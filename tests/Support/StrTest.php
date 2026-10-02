<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Tests\Support;

use Kontirol\ApiDoc\Support\Str;
use PHPUnit\Framework\TestCase;

final class StrTest extends TestCase
{
    public function testStartsWith(): void
    {
        self::assertTrue(Str::startsWith('Controller.php', 'Controller'));
        self::assertFalse(Str::startsWith('Controller.php', 'controller'));
        self::assertTrue(Str::startsWith('x', ''));
    }

    public function testEndsWith(): void
    {
        self::assertTrue(Str::endsWith('Controller.php', '.php'));
        self::assertFalse(Str::endsWith('Controller.php', '.PHP'));
        self::assertTrue(Str::endsWith('x', ''));
        self::assertFalse(Str::endsWith('ab', 'abc'));
    }

    public function testContains(): void
    {
        self::assertTrue(Str::contains('abc', 'b'));
        self::assertFalse(Str::contains('abc', 'z'));
        self::assertTrue(Str::contains('abc', ''));
    }

    public function testLinesSplitsHandlesAllLineEndingsAndTrims(): void
    {
        self::assertSame(['a', 'b'], Str::lines("  a  \n\n b \r\n"));
    }

    public function testLinesDropsEmptyLines(): void
    {
        self::assertSame([], Str::lines("\n\n   \n"));
    }

    public function testLinesKeepsMultiByteCharactersIntact(): void
    {
        // "情" is E6 83 85 in UTF-8; the trailing 0x85 byte is what \R used to
        // mistake for a line break.
        self::assertSame(['用户详情'], Str::lines("用户详情\n"));

        foreach (Str::lines("用户详情\n订单详情") as $line) {
            self::assertTrue(mb_check_encoding($line, 'UTF-8'));
        }
    }

    public function testSqueezeCollapsesWhitespace(): void
    {
        self::assertSame('a b c', Str::squeeze("  a\t b\n\n c  "));
    }

    public function testSqueezeKeepsMultiByteCharacters(): void
    {
        self::assertSame('用户 详情', Str::squeeze("用户   详情"));
    }
}
