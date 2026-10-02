<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Tests\Config;

use Kontirol\ApiDoc\Config\ConfigLoader;
use Kontirol\ApiDoc\Exception\ConfigException;
use Kontirol\ApiDoc\Support\Path;
use PHPUnit\Framework\TestCase;

final class ConfigLoaderTest extends TestCase
{
    /**
     * @var list<string>
     */
    private $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        $this->files = [];
    }

    public function testLoadsPhpConfiguration(): void
    {
        $path = $this->writeTemp('php', '<?php return ["info" => ["title" => "Demo"], "controllers" => "app"];');

        $config = ConfigLoader::load($path);

        self::assertSame('Demo', $config->info()['title']);
        self::assertSame('1.0.0', $config->info()['version']);
    }

    public function testLoadsJsonConfiguration(): void
    {
        $path = $this->writeTemp('json', '{"controllers": "app", "info": {"title": "Json Demo"}}');

        $config = ConfigLoader::load($path);

        self::assertSame('Json Demo', $config->info()['title']);
    }

    public function testLoadsYamlConfiguration(): void
    {
        $path = $this->writeTemp('yaml', "controllers: app\ninfo:\n  title: Yaml Demo\n");

        $config = ConfigLoader::load($path);

        self::assertSame('Yaml Demo', $config->info()['title']);
    }

    public function testBasePathIsTheDirectoryOfTheConfigFile(): void
    {
        $path = $this->writeTemp('json', '{"controllers": "app"}');

        $config = ConfigLoader::load($path);

        self::assertSame(
            Path::normalize(dirname((string) realpath($path))),
            $config->basePath()
        );
    }

    public function testMissingFileThrows(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('not found');

        ConfigLoader::load(__DIR__ . '/does-not-exist.php');
    }

    public function testUnsupportedExtensionThrows(): void
    {
        $path = $this->writeTemp('ini', 'controllers = app');

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Unsupported configuration format');

        ConfigLoader::load($path);
    }

    public function testInvalidJsonThrows(): void
    {
        $path = $this->writeTemp('json', '{"controllers": ');

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('invalid JSON');

        ConfigLoader::load($path);
    }

    public function testPhpFileReturningNonArrayThrows(): void
    {
        $path = $this->writeTemp('php', '<?php return "nope";');

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('must return an array');

        ConfigLoader::load($path);
    }

    public function testEmptyYamlIsTreatedAsEmptyConfiguration(): void
    {
        $path = $this->writeTemp('yml', '');

        $config = ConfigLoader::load($path);

        self::assertSame([], $config->controllers());
    }

    private function writeTemp(string $extension, string $content): string
    {
        $path = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'apidoc_cfg_' . bin2hex(random_bytes(8)) . '.' . $extension;

        file_put_contents($path, $content);

        $this->files[] = $path;

        return $path;
    }
}
