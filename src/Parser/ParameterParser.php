<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Parser;

use Kontirol\ApiDoc\Model\Diagnostic;
use Kontirol\ApiDoc\Model\Parameter;

/**
 * Turns a "param" tag into a Parameter.
 *
 * Supported syntax. The leading "@" is left out of the examples on purpose so
 * that static analysers do not mistake them for real annotations:
 *
 *     int       page      页码，默认1
 *     string[]  ids       ID 列表
 *     int!      id        订单 ID  <- "!" marks the parameter as required
 *     int       page=1    页码     <- inline default value
 */
final class ParameterParser
{
    /**
     * @param list<Diagnostic> $diagnostics
     */
    public function parse(TagValue $tag, ?string $file, array &$diagnostics): ?Parameter
    {
        $parts = $tag->parts();

        if (count($parts) < 2) {
            $diagnostics[] = Diagnostic::warning(
                'param.malformed',
                sprintf(
                    'Ignored malformed @%s "%s". Expected: @%s <type> <name> <description>',
                    $tag->name,
                    $tag->value,
                    $tag->name
                ),
                $file,
                $tag->line
            );

            return null;
        }

        $type = array_shift($parts);
        $name = array_shift($parts);
        $description = trim(implode(' ', $parts));

        $required = false;

        if (strpos($type, '!') !== false) {
            $required = true;
            $type = str_replace('!', '', $type);
        }

        $default = null;
        $hasDefault = false;
        $position = strpos($name, '=');

        if ($position !== false) {
            $default = substr($name, $position + 1);
            $name = substr($name, 0, $position);
            $hasDefault = true;
        }

        $name = ltrim(trim($name), '$');

        if ($name === '') {
            $diagnostics[] = Diagnostic::warning(
                'param.missing_name',
                sprintf('Ignored @%s "%s": the parameter name is empty.', $tag->name, $tag->value),
                $file,
                $tag->line
            );

            return null;
        }

        $parameter = new Parameter($name, self::mapType($type), $description);
        $parameter->required = $required;
        $parameter->hasDefault = $hasDefault;

        if ($hasDefault) {
            $parameter->default = self::castDefault((string) $default, $parameter->type);
        }

        return $parameter;
    }

    /**
     * Maps a PHPDoc style type to an OpenAPI primitive type.
     */
    public static function mapType(string $type): string
    {
        $type = strtolower(trim($type));

        if (strpos($type, '|') !== false) {
            foreach (explode('|', $type) as $member) {
                $member = trim($member);

                if ($member !== '' && $member !== 'null') {
                    $type = $member;

                    break;
                }
            }
        }

        $type = trim($type, '?');

        if (substr($type, -2) === '[]') {
            return 'array';
        }

        switch ($type) {
            case 'int':
            case 'integer':
                return 'integer';
            case 'float':
            case 'double':
            case 'number':
            case 'decimal':
                return 'number';
            case 'bool':
            case 'boolean':
                return 'boolean';
            case 'array':
            case 'list':
            case 'object[]':
                return 'array';
            case 'object':
            case 'json':
            case 'map':
                return 'object';
            default:
                return 'string';
        }
    }

    /**
     * @return mixed
     */
    private static function castDefault(string $value, string $type)
    {
        $value = trim($value, "\"'");

        if ($type === 'integer') {
            return (int) $value;
        }

        if ($type === 'number') {
            return (float) $value;
        }

        if ($type === 'boolean') {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        return $value;
    }
}
