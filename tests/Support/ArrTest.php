<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Tests\Support;

use Kontirol\ApiDoc\Support\Arr;
use PHPUnit\Framework\TestCase;

final class ArrTest extends TestCase
{
    public function testGetReadsDotNotation(): void
    {
        $data = ['scan' => ['suffix' => 'Controller.php']];

        self::assertSame('Controller.php', Arr::get($data, 'scan.suffix'));
    }

    public function testGetReturnsTheDefaultForMissingKeys(): void
    {
        self::assertNull(Arr::get([], 'a.b'));
        self::assertSame('x', Arr::get([], 'a.b', 'x'));
    }

    public function testGetReturnsTheWholeArrayForAnEmptyKey(): void
    {
        $data = ['a' => 1];

        self::assertSame($data, Arr::get($data, ''));
    }

    public function testGetPrefersAnExactKeyOverDotNotation(): void
    {
        $data = ['a.b' => 1, 'a' => ['b' => 2]];

        self::assertSame(1, Arr::get($data, 'a.b'));
    }

    public function testGetStopsWhenALevelIsNotAnArray(): void
    {
        self::assertSame('fallback', Arr::get(['a' => 'scalar'], 'a.b', 'fallback'));
    }

    public function testHasDistinguishesNullFromMissing(): void
    {
        self::assertTrue(Arr::has(['a' => ['b' => null]], 'a.b'));
        self::assertFalse(Arr::has(['a' => ['b' => null]], 'a.c'));
    }

    public function testMergeOverridesScalars(): void
    {
        self::assertSame(['a' => 2], Arr::merge(['a' => 1], ['a' => 2]));
    }

    public function testMergeIsRecursiveForAssociativeArrays(): void
    {
        $base = ['scan' => ['suffix' => 'Controller.php', 'exclude' => ['vendor']]];
        $override = ['scan' => ['exclude' => ['tests']]];

        self::assertSame(
            ['scan' => ['suffix' => 'Controller.php', 'exclude' => ['tests']]],
            Arr::merge($base, $override)
        );
    }

    public function testListsAreReplacedNotConcatenated(): void
    {
        self::assertSame(['a' => [3]], Arr::merge(['a' => [1, 2]], ['a' => [3]]));
    }

    public function testNullDisablesADefault(): void
    {
        self::assertSame(['a' => null], Arr::merge(['a' => 'x'], ['a' => null]));
    }

    public function testMergeAddsUnknownKeys(): void
    {
        self::assertSame(['a' => 1, 'b' => 2], Arr::merge(['a' => 1], ['b' => 2]));
    }

    public function testIsAssoc(): void
    {
        self::assertTrue(Arr::isAssoc(['a' => 1]));
        self::assertFalse(Arr::isAssoc([1, 2]));
        self::assertFalse(Arr::isAssoc([]));
    }

    public function testIsAssocIsTrueForNonSequentialKeys(): void
    {
        self::assertTrue(Arr::isAssoc([1 => 'a', 3 => 'b']));
    }

    public function testFirstKey(): void
    {
        self::assertSame('a', Arr::firstKey(['a' => 1, 'b' => 2]));
        self::assertNull(Arr::firstKey([]));
    }
}
