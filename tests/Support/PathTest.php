<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Tests\Support;

use Kontirol\ApiDoc\Support\Path;
use PHPUnit\Framework\TestCase;

final class PathTest extends TestCase
{
    public function testIsAbsoluteDetectsUnixPaths(): void
    {
        self::assertTrue(Path::isAbsolute('/var/www'));
        self::assertFalse(Path::isAbsolute('var/www'));
    }

    public function testIsAbsoluteDetectsWindowsPaths(): void
    {
        self::assertTrue(Path::isAbsolute('D:\\code\\apidoc'));
        self::assertTrue(Path::isAbsolute('D:/code/apidoc'));
        self::assertFalse(Path::isAbsolute('code/apidoc'));
    }

    public function testIsAbsoluteRejectsEmptyString(): void
    {
        self::assertFalse(Path::isAbsolute(''));
    }

    public function testResolveKeepsAbsolutePaths(): void
    {
        self::assertSame(
            Path::normalize('/var/www/app'),
            Path::resolve('/base', '/var/www/app')
        );
    }

    public function testResolveJoinsRelativePaths(): void
    {
        self::assertSame(
            Path::normalize('/base/app/Controller'),
            Path::resolve('/base', 'app/Controller')
        );
    }

    public function testResolveIgnoresATrailingSlashInTheBasePath(): void
    {
        self::assertSame(
            Path::resolve('/base', 'app'),
            Path::resolve('/base/', 'app')
        );
    }

    public function testNormalizeConvertsSeparators(): void
    {
        $expected = DIRECTORY_SEPARATOR === '\\' ? 'a\\b\\c' : 'a/b/c';

        self::assertSame($expected, Path::normalize('a/b/c'));
    }

    public function testNormalizeCollapsesParentSegments(): void
    {
        self::assertSame(
            Path::normalize('D:/code/apidoc/build/openapi.json'),
            Path::normalize('D:/code/apidoc/examples/../build/./openapi.json')
        );
    }

    public function testNormalizeKeepsLeadingParentSegments(): void
    {
        self::assertSame('..' . DIRECTORY_SEPARATOR . 'x', Path::normalize('../x'));
    }

    public function testNormalizeCollapsesDotSegments(): void
    {
        self::assertSame('a' . DIRECTORY_SEPARATOR . 'b', Path::normalize('./a/./b'));
    }

    public function testNormalizeKeepsTheRoot(): void
    {
        self::assertSame(DIRECTORY_SEPARATOR, Path::normalize('/'));
    }

    public function testNormalizeReturnsEmptyStringForEmptyInput(): void
    {
        self::assertSame('', Path::normalize(''));
    }

    public function testToUnixAlwaysUsesForwardSlashes(): void
    {
        self::assertSame('a/b/c', Path::toUnix('a\\b\\c'));
    }

    public function testEqualsIgnoresTrailingSlash(): void
    {
        self::assertTrue(Path::equals('/var/www/', '/var/www'));
        self::assertFalse(Path::equals('/var/www', '/var/lib'));
    }

    public function testIsWithin(): void
    {
        self::assertTrue(Path::isWithin('/var/www/app', '/var/www'));
        self::assertTrue(Path::isWithin('/var/www', '/var/www'));
        self::assertFalse(Path::isWithin('/var/lib', '/var/www'));
        self::assertFalse(Path::isWithin('/var/www2', '/var/www'));
    }

    public function testRelative(): void
    {
        self::assertSame('app/Controller', Path::relative('/var/www', '/var/www/app/Controller'));
        self::assertSame('/other/path', Path::relative('/var/www', '/other/path'));
    }
}
