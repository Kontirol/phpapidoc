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
            self::methodNames($class->methods)
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

    /**
     * Method names may legally be one of PHP's semi reserved words. The
     * tokenizer reports those as their own token (T_LIST, T_PRINT, ...) rather
     * than T_STRING, and refusing them used to drop the whole method without a
     * word of warning.
     */
    public function testSemiReservedWordMethodNamesAreRecognised(): void
    {
        $code = <<<'CODE'
<?php
namespace App;

class Health
{
    /** @name 列表 */
    public function list(): void {}

    /** @name 打印 */
    public function print(): void {}

    /** @name 默认项 */
    public function default(): void {}

    /** @name 引入 */
    public function include(): void {}

    /** @name 空判断 */
    public function empty(): void {}

    /** @name 克隆 */
    public function clone(): void {}
}
CODE;

        $class = $this->scanner->scan($code)[0];

        self::assertSame(
            ['list', 'print', 'default', 'include', 'empty', 'clone'],
            self::methodNames($class->methods)
        );

        // The docblock still has to land on the right method.
        self::assertStringContainsString('@name 列表', (string) $class->methods[0]->docBlock);
        self::assertStringContainsString('@name 克隆', (string) $class->methods[5]->docBlock);
    }

    public function testSemiReservedWordMethodNamesKeepTheirModifiers(): void
    {
        $code = <<<'CODE'
<?php

class Foo
{
    public static function list(): void {}

    protected function print(): void {}

    private function unset(): void {}
}
CODE;

        $methods = $this->scanner->scan($code)[0]->methods;

        self::assertSame(['list', 'print', 'unset'], self::methodNames($methods));
        self::assertSame('public', $methods[0]->visibility);
        self::assertTrue($methods[0]->isStatic);
        self::assertSame('protected', $methods[1]->visibility);
        self::assertSame('private', $methods[2]->visibility);
    }

    public function testReferenceReturningSemiReservedMethodIsRecognised(): void
    {
        $code = <<<'CODE'
<?php

class Foo
{
    /** @name 引用返回 */
    public function &list(): array
    {
        $x = [];

        return $x;
    }
}
CODE;

        $methods = $this->scanner->scan($code)[0]->methods;

        self::assertSame(['list'], self::methodNames($methods));
        self::assertStringContainsString('@name 引用返回', (string) $methods[0]->docBlock);
    }

    public function testHandlesCodeWithoutClasses(): void
    {
        self::assertSame([], $this->scanner->scan('<?php echo 1;'));
    }

    /**
     * @param list<\Kontirol\ApiDoc\Parser\ParsedMethod> $methods
     *
     * @return list<string>
     */
    private static function methodNames(array $methods): array
    {
        $names = [];

        foreach ($methods as $method) {
            $names[] = $method->name;
        }

        return $names;
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
