<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Tests\Cli;

use Kontirol\ApiDoc\Cli\Console;
use Kontirol\ApiDoc\Cli\Output;
use PHPUnit\Framework\TestCase;

final class ConsoleTest extends TestCase
{
    /**
     * @var resource
     */
    private $stdout;

    /**
     * @var resource
     */
    private $stderr;

    /**
     * @var string
     */
    private $outputPath = '';

    /**
     * @var list<string>
     */
    private $files = [];

    protected function setUp(): void
    {
        $this->stdout = fopen('php://memory', 'r+');
        $this->stderr = fopen('php://memory', 'r+');
    }

    protected function tearDown(): void
    {
        fclose($this->stdout);
        fclose($this->stderr);

        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        $this->files = [];
    }

    public function testGenerateWritesTheSpecification(): void
    {
        $config = $this->configFile();

        $code = $this->console()->run(['apidoc', 'generate', '-c', $config]);

        self::assertSame(Console::SUCCESS, $code);
        self::assertStringContainsString('endpoint(s) documented', $this->stdout());
        self::assertStringContainsString('Written:', $this->stdout());
        self::assertFileExists($this->outputPath);

        $spec = json_decode((string) file_get_contents($this->outputPath), true);

        self::assertSame('CLI Fixture', $spec['info']['title']);
        self::assertArrayHasKey('/user/list', $spec['paths']);
    }

    public function testValidateDoesNotWriteAnything(): void
    {
        $config = $this->configFile();

        $code = $this->console()->run(['apidoc', 'validate', '-c', $config]);

        self::assertSame(Console::SUCCESS, $code);
        self::assertStringContainsString('endpoint(s) documented', $this->stdout());
        self::assertStringContainsString('No problems found.', $this->stdout());
        self::assertFileDoesNotExist($this->outputPath);
    }

    public function testVersionIsPrinted(): void
    {
        $code = $this->console()->run(['apidoc', 'version']);

        self::assertSame(Console::SUCCESS, $code);
        self::assertStringContainsString(Console::VERSION, $this->stdout());
    }

    public function testHelpIsPrinted(): void
    {
        $code = $this->console()->run(['apidoc', 'help']);

        self::assertSame(Console::SUCCESS, $code);
        self::assertStringContainsString('Usage:', $this->stdout());
        self::assertStringContainsString('--no-reflection', $this->stdout());
    }

    public function testHelpFlagWinsOverEverythingElse(): void
    {
        $code = $this->console()->run(['apidoc', 'generate', '--help']);

        self::assertSame(Console::SUCCESS, $code);
        self::assertStringContainsString('Usage:', $this->stdout());
    }

    public function testNoArgumentsPrintsHelp(): void
    {
        $code = $this->console()->run(['apidoc']);

        self::assertSame(Console::SUCCESS, $code);
        self::assertStringContainsString('Usage:', $this->stdout());
    }

    public function testUnknownCommandFails(): void
    {
        $code = $this->console()->run(['apidoc', 'frobnicate']);

        self::assertSame(Console::FAILURE, $code);
        self::assertStringContainsString('Unknown command', $this->stderr());
    }

    public function testMissingConfigurationFileFails(): void
    {
        $code = $this->console()->run(['apidoc', 'generate', '-c', 'does-not-exist.php']);

        self::assertSame(Console::FAILURE, $code);
        self::assertStringContainsString('error:', $this->stderr());
    }

    public function testQuietSuppressesEverythingButErrors(): void
    {
        $config = $this->configFile();

        $code = $this->console()->run(['apidoc', 'generate', '-c', $config, '--quiet']);

        self::assertSame(Console::SUCCESS, $code);
        self::assertSame('', $this->stdout());
    }

    public function testVerboseListsTheScannedDirectories(): void
    {
        $config = $this->configFile();

        $this->console()->run(['apidoc', 'generate', '-c', $config, '-v']);

        self::assertStringContainsString('Scanning', $this->stdout());
        self::assertStringContainsString('Output json:', $this->stdout());
    }

    private function console(): Console
    {
        return new Console(new Output(Output::NORMAL, $this->stdout, $this->stderr));
    }

    private function stdout(): string
    {
        return $this->read($this->stdout);
    }

    private function stderr(): string
    {
        return $this->read($this->stderr);
    }

    /**
     * @param resource $stream
     */
    private function read($stream): string
    {
        rewind($stream);

        return (string) stream_get_contents($stream);
    }

    private function configFile(): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'apidoc_cfg_' . bin2hex(random_bytes(6)) . '.php';
        $this->outputPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'apidoc_spec_' . bin2hex(random_bytes(6)) . '.json';

        $config = [
            'controllers' => [
                ['path' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Fixtures' . DIRECTORY_SEPARATOR . 'Controllers'],
            ],
            'output' => ['json' => $this->outputPath],
            'info' => ['title' => 'CLI Fixture', 'version' => '0.1.0'],
        ];

        file_put_contents($path, '<?php return ' . var_export($config, true) . ';');

        $this->files[] = $path;
        $this->files[] = $this->outputPath;

        return $path;
    }
}
