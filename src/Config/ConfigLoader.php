<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Config;

use Kontirol\ApiDoc\Exception\ConfigException;
use Symfony\Component\Yaml\Exception\ParseException as YamlParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads a configuration file (PHP, JSON or YAML) into a Config object.
 */
final class ConfigLoader
{
    /**
     * @var list<string>
     */
    public const SUPPORTED_EXTENSIONS = ['php', 'json', 'yaml', 'yml'];

    private function __construct()
    {
    }

    public static function load(string $path): Config
    {
        if (!is_file($path)) {
            throw ConfigException::fileNotFound($path);
        }

        $real = realpath($path);

        if ($real === false) {
            throw ConfigException::unreadable($path, 'realpath() returned false');
        }

        return new Config(self::parseFile($real), dirname($real));
    }

    /**
     * @return array<string, mixed>
     */
    public static function parseFile(string $path): array
    {
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        switch ($extension) {
            case 'php':
                return self::parsePhp($path);
            case 'json':
                return self::parseJson($path);
            case 'yaml':
            case 'yml':
                return self::parseYaml($path);
        }

        throw ConfigException::unsupportedFormat($extension, self::SUPPORTED_EXTENSIONS);
    }

    /**
     * @return array<string, mixed>
     */
    private static function parsePhp(string $path): array
    {
        $data = require $path;

        if (!is_array($data)) {
            throw ConfigException::unreadable($path, 'the PHP config file must return an array');
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private static function parseJson(string $path): array
    {
        $content = @file_get_contents($path);

        if ($content === false) {
            throw ConfigException::unreadable($path, 'the file could not be read');
        }

        $data = json_decode($content, true);

        if (!is_array($data)) {
            throw ConfigException::unreadable($path, 'invalid JSON: ' . json_last_error_msg());
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private static function parseYaml(string $path): array
    {
        if (!class_exists(Yaml::class)) {
            throw ConfigException::unreadable(
                $path,
                'symfony/yaml is required to read YAML configuration. Run: composer require symfony/yaml'
            );
        }

        try {
            $data = Yaml::parseFile($path);
        } catch (YamlParseException $exception) {
            throw ConfigException::unreadable($path, $exception->getMessage());
        }

        if ($data === null) {
            return [];
        }

        if (!is_array($data)) {
            throw ConfigException::unreadable($path, 'the YAML config file must contain a mapping');
        }

        return $data;
    }
}
