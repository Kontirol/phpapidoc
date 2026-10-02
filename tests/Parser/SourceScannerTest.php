<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Tests\Parser;

use Kontirol\ApiDoc\Parser\SourceScanner;
use PHPUnit\Framework\TestCase;

final class SourceScannerTest extends TestCase
{
    /**
     * @var SourceScanner
     */
    private $scanner;

    protected function setUp(): void
    {
        $this->scanner = new SourceScanner();
    }

    public function testReadsNamespaceAndClassName(): void
    {
        $classes = $this->scanFixture('UserController.php');

        self::assertCount(1, $classes);
        self::assertSame('UserController', $classes[0]->name);
        self::assertSame('Kontirol\\ApiDoc\\Tests\\Fixtures\\Controllers', $classes[0]->namespace);
        self::assertSame(
            'Kontirol\\ApiDoc\\Tests\\Fixtures\\Controllers\\UserController',
            $classes[0]->fqcn()
        );
    }

    public function testBindsDocBlocksToMethodsOnly(): void
    {
        $class = $this->scanFixture('UserController.php')[0];

        self::assertSame(
            ['index', 'detail', 'create', 'internal', 'notDocumented'],
            array_map(static function ($method): string {
                return $method->name;
            }, $class->methods)
        );

        $index = $class->methods[0];

        self::assertTrue($index->hasDocBlock());
        self::assertStringContainsString('@route /user/list', (string) $index->docBlock);
        self::assertStringNotContainsString('Class level docblock', (string) $index->docBlock);
    }

    public function testMethodWithoutDocBlockHasNoDocBlock(): void
    {
        $class = $this->scanFixture('UserController.php')[0];

        self::assertFalse($class->methods[4]->hasDocBlock());
        self::assertNull($class->methods[4]->docBlock);
    }

    public function testReportsDocBlockLineNumbers(): void
    {
        $class = $this->scanFixture('OrderController.php')[0];

        self::assertSame('detail', $class->methods[0]->name);
        self::assertGreaterThan(1, $class->methods[0]->line);
    }

    public function testDetectsVisibilityAndStatic(): void
    {
        $code = <<<'CODE'
<?php
namespace App;

class Foo
{
    public static function a(): void {}

    protected function b(): void {}

    private function c(): void {}
}
CODE;

        $methods = $this->scanner->scan($code)[0]->methods;

        self::assertCount(3, $methods);
        self::assertSame('public', $methods[0]->visibility);
        self::assertTrue($methods[0]->isStatic);
        self::assertSame('protected', $methods[1]->visibility);
        self::assertFalse($methods[1]->isStatic);
        self::assertSame('private', $methods[2]->visibility);
    }

    public function testPropertyDocBlockIsNotAttachedToAMethod(): void
    {
        $code = <<<'CODE'
<?php

class Foo
{
    /** @var string */
    private $bar = 'x';

    public function baz(): void {}
}
CODE;

        $methods = $this->scanner->scan($code)[0]->methods;

        self::assertCount(1, $methods);
        self::assertNull($methods[0]->docBlock);
    }

    public function testClosuresAreNotReportedAsMethods(): void
    {
        $code = <<<'CODE'
<?php

class Foo
{
    public function bar(): void
    {
        $fn = function (): void {
        };

        $fn2 = static function (): void {
        };
    }
}
CODE;

        $methods = $this->scanner->scan($code)[0]->methods;

        self::assertCount(1, $methods);
        self::assertSame('bar', $methods[0]->name);
    }

    public function testAnonymousClassMethodsAreIgnored(): void
    {
        $code = <<<'CODE'
<?php

class Foo
{
    /** @name 真实接口 */
    public function bar(): void
    {
        $x = new class {
            /** @name 匿名类里的方法 */
            public function inner(): void {}
        };
    }
}
CODE;

        $classes = $this->scanner->scan($code);

        self::assertCount(1, $classes);
        self::assertCount(1, $classes[0]->methods);
        self::assertSame('bar', $classes[0]->methods[0]->name);
    }

    public function testReturnsEveryClassOfTheFile(): void
    {
        $code = <<<'CODE'
<?php
namespace App;

class First
{
    public function a(): void {}
}

class Second
{
    public function b(): void {}
}
CODE;

        $classes = $this->scanner->scan($code);

        self::assertCount(2, $classes);
        self::assertSame('First', $classes[0]->name);
        self::assertSame('Second', $classes[1]->name);
        self::assertSame('a', $classes[0]->methods[0]->name);
        self::assertSame('b', $classes[1]->methods[0]->name);
    }

    public function testHandlesCodeWithoutClasses(): void
    {
        self::assertSame([], $this->scanner->scan('<?php echo 1;'));
    }

    /**
     * @return list<\Kontirol\ApiDoc\Parser\ParsedClass>
     */
    private function scanFixture(string $name): array
    {
        $path = __DIR__ . '/../Fixtures/Controllers/' . $name;
        $code = file_get_contents($path);

        self::assertIsString($code);

        return $this->scanner->scan($code);
    }
}
