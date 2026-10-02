<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Tests\Output;

use Kontirol\ApiDoc\Exception\OutputException;
use Kontirol\ApiDoc\Output\DocumentWriter;
use PHPUnit\Framework\TestCase;

final class DocumentWriterTest extends TestCase
{
    /**
     * @var list<string>
     */
    private $files = [];

    /**
     * @var list<string>
     */
    private $directories = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        foreach ($this->directories as $directory) {
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }

        $this->files = [];
        $this->directories = [];
    }

    public function testWritesJsonToDisk(): void
    {
        $path = $this->tempPath('json');

        $written = (new DocumentWriter())->write(
            ['openapi' => '3.0.3', 'paths' => []],
            [['format' => 'json', 'path' => $path]]
        );

        self::assertSame([$path], $written);
        self::assertFileExists($path);

        $decoded = json_decode((string) file_get_contents($path), true);

        self::assertSame('3.0.3', $decoded['openapi']);
    }

    public function testWritesYamlToDisk(): void
    {
        $path = $this->tempPath('yaml');

        (new DocumentWriter())->write(['openapi' => '3.0.3'], [['format' => 'yaml', 'path' => $path]]);

        $content = (string) file_get_contents($path);

        self::assertStringContainsString('openapi: 3.0.3', $content);
    }

    public function testWritesSeveralTargetsAtOnce(): void
    {
        $json = $this->tempPath('json');
        $yaml = $this->tempPath('yaml');

        $written = (new DocumentWriter())->write(['openapi' => '3.0.3'], [
            ['format' => 'json', 'path' => $json],
            ['format' => 'yaml', 'path' => $yaml],
        ]);

        self::assertCount(2, $written);
        self::assertFileExists($json);
        self::assertFileExists($yaml);
    }

    public function testMissingDirectoriesAreCreated(): void
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'apidoc_out_' . bin2hex(random_bytes(6));
        $path = $directory . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'api.json';

        (new DocumentWriter())->write(['a' => 1], [['format' => 'json', 'path' => $path]]);

        self::assertFileExists($path);

        unlink($path);
        rmdir($directory . DIRECTORY_SEPARATOR . 'nested');
        rmdir($directory);
    }

    public function testJsonKeepsUnicodeAndIsPrettyPrinted(): void
    {
        $json = (new DocumentWriter())->encode(['title' => '用户中心'], 'json');

        self::assertStringContainsString('用户中心', $json);
        self::assertStringContainsString("\n", $json);
        self::assertStringNotContainsString('\\u', $json);
    }

    public function testUnknownFormatThrows(): void
    {
        $this->expectException(OutputException::class);
        $this->expectExceptionMessage('Unsupported output format');

        (new DocumentWriter())->encode([], 'xml');
    }

    public function testUnwritablePathThrows(): void
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'apidoc_dir_' . bin2hex(random_bytes(6));
        mkdir($directory);
        $this->directories[] = $directory;

        $this->expectException(OutputException::class);
        $this->expectExceptionMessage('could not be written');

        (new DocumentWriter())->write([], [['format' => 'json', 'path' => $directory]]);
    }

    private function tempPath(string $extension): string
    {
        $path = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'apidoc_out_' . bin2hex(random_bytes(8)) . '.' . $extension;

        $this->files[] = $path;

        return $path;
    }
}
