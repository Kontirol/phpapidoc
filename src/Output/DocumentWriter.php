<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Output;

use Kontirol\ApiDoc\Config\Config;
use Kontirol\ApiDoc\Exception\OutputException;
use Symfony\Component\Yaml\Yaml;

/**
 * Serialises a generated specification to JSON and/or YAML files.
 */
final class DocumentWriter
{
    /**
     * @param array<string, mixed>                      $spec
     * @param list<array{format: string, path: string}> $targets
     *
     * @return list<string> The absolute paths that were written.
     */
    public function write(array $spec, array $targets): array
    {
        $written = [];

        foreach ($targets as $target) {
            $this->ensureDirectory(dirname($target['path']));

            $content = $this->encode($spec, $target['format']);

            if (@file_put_contents($target['path'], $content) === false) {
                throw OutputException::unwritable($target['path'], 'the file could not be written');
            }

            $written[] = $target['path'];
        }

        return $written;
    }

    /**
     * @param array<string, mixed> $spec
     */
    public function encode(array $spec, string $format): string
    {
        if ($format === Config::FORMAT_JSON) {
            return $this->encodeJson($spec);
        }

        if ($format === Config::FORMAT_YAML) {
            return $this->encodeYaml($spec);
        }

        throw OutputException::unsupportedFormat($format, [Config::FORMAT_JSON, Config::FORMAT_YAML]);
    }

    /**
     * @param array<string, mixed> $spec
     */
    private function encodeJson(array $spec): string
    {
        $json = json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw OutputException::encodeFailed('json', json_last_error_msg());
        }

        return $json . "\n";
    }

    /**
     * @param array<string, mixed> $spec
     */
    private function encodeYaml(array $spec): string
    {
        if (!class_exists(Yaml::class)) {
            throw OutputException::encodeFailed('yaml', 'symfony/yaml is not installed');
        }

        return Yaml::dump($spec, 20, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);
    }

    private function ensureDirectory(string $directory): void
    {
        if ($directory === '' || $directory === '.' || is_dir($directory)) {
            return;
        }

        if (!@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw OutputException::unwritable($directory, 'the directory could not be created');
        }
    }
}
