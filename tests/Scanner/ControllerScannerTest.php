<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Tests\Scanner;

use Kontirol\ApiDoc\Exception\ScanException;
use Kontirol\ApiDoc\Scanner\ControllerScanner;
use Kontirol\ApiDoc\Support\Path;
use PHPUnit\Framework\TestCase;

final class ControllerScannerTest extends TestCase
{
    /**
     * @var string
     */
    private $fixtures;

    protected function setUp(): void
    {
        $this->fixtures = Path::normalize((string) realpath(__DIR__ . '/../Fixtures/Controllers'));
    }

    public function testScansControllersRecursively(): void
    {
        self::assertSame([
            'Admin/RoleController.php',
            'BaseController.php',
            'OrderController.php',
            'UserController.php',
            'vendor/SkipController.php',
        ], $this->relativePaths(new ControllerScanner(), []));
    }

    public function testOnlyFilesMatchingTheSuffixAreReturned(): void
    {
        self::assertSame(
            ['Admin/RoleController.php'],
            $this->relativePaths(new ControllerScanner(), [], 'RoleController.php')
        );
    }

    public function testExcludesDirectoryByBareName(): void
    {
        $paths = $this->relativePaths(new ControllerScanner(), ['vendor']);

        self::assertNotContains('vendor/SkipController.php', $paths);
        self::assertContains('OrderController.php', $paths);
    }

    public function testExcludesFileByBaseName(): void
    {
        $paths = $this->relativePaths(new ControllerScanner(), ['BaseController']);

        self::assertNotContains('BaseController.php', $paths);
        self::assertContains('OrderController.php', $paths);
    }

    public function testExcludesWithGlobPattern(): void
    {
        $paths = $this->relativePaths(new ControllerScanner(), ['Admin/*']);

        self::assertNotContains('Admin/RoleController.php', $paths);
        self::assertContains('UserController.php', $paths);
    }

    public function testGlobalExcludePatternsAreAppliedToEverySource(): void
    {
        self::assertNotContains(
            'vendor/SkipController.php',
            $this->relativePaths(new ControllerScanner(['vendor']), [])
        );
    }

    public function testPassesPrefixNamespaceAndRootToFiles(): void
    {
        $files = (new ControllerScanner())->scan([[
            'path' => $this->fixtures,
            'namespace' => 'app\\api\\controller',
            'prefix' => '/api',
            'exclude' => ['vendor', 'BaseController'],
            'suffix' => 'Controller.php',
        ]]);

        self::assertNotEmpty($files);
        self::assertSame('/api', $files[0]->prefix);
        self::assertSame('app\\api\\controller', $files[0]->namespace);
        self::assertSame($this->fixtures, $files[0]->root);
        self::assertStringStartsWith($this->fixtures, $files[0]->path);
    }

    public function testDuplicatedSourcesAreCollectedOnce(): void
    {
        $source = [
            'path' => $this->fixtures,
            'namespace' => null,
            'prefix' => '',
            'exclude' => [],
            'suffix' => 'Controller.php',
        ];

        self::assertCount(5, (new ControllerScanner())->scan([$source, $source]));
    }

    public function testMissingDirectoryThrows(): void
    {
        $this->expectException(ScanException::class);

        (new ControllerScanner())->scan([[
            'path' => $this->fixtures . '/does-not-exist',
            'namespace' => null,
            'prefix' => '',
            'exclude' => [],
            'suffix' => 'Controller.php',
        ]]);
    }

    /**
     * @param list<string> $exclude
     *
     * @return list<string>
     */
    private function relativePaths(ControllerScanner $scanner, array $exclude, string $suffix = 'Controller.php'): array
    {
        $files = $scanner->scan([[
            'path' => $this->fixtures,
            'namespace' => null,
            'prefix' => '',
            'exclude' => $exclude,
            'suffix' => $suffix,
        ]]);

        $paths = [];

        foreach ($files as $file) {
            $paths[] = $file->relativePath;
        }

        sort($paths);

        return $paths;
    }
}
