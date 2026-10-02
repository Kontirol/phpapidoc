<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Model;

/**
 * A single documented parameter.
 *
 * This is a plain data holder: the parser fills it in, the OpenAPI builder
 * reads it back. It carries no behaviour beyond trivial queries.
 */
final class Parameter
{
    public const IN_QUERY = 'query';
    public const IN_PATH = 'path';
    public const IN_HEADER = 'header';
    public const IN_COOKIE = 'cookie';

    /**
     * @var list<string>
     */
    public const LOCATIONS = [self::IN_QUERY, self::IN_PATH, self::IN_HEADER, self::IN_COOKIE];

    /**
     * @var string
     */
    public $name;

    /**
     * OpenAPI type: string, integer, number, boolean, array or object.
     *
     * @var string
     */
    public $type = 'string';

    /**
     * @var string
     */
    public $description = '';

    /**
     * @var bool
     */
    public $required = false;

    /**
     * @var string One of the IN_* constants.
     */
    public $in = self::IN_QUERY;

    /**
     * @var mixed
     */
    public $default;

    /**
     * @var bool
     */
    public $hasDefault = false;

    /**
     * @var mixed
     */
    public $example;

    /**
     * @var bool
     */
    public $hasExample = false;

    /**
     * @var list<string>|null
     */
    public $enum;

    /**
     * @var bool
     */
    public $deprecated = false;

    public function __construct(string $name, string $type = 'string', string $description = '')
    {
        $this->name = $name;
        $this->type = $type;
        $this->description = $description;
    }

    public function isInPath(): bool
    {
        return $this->in === self::IN_PATH;
    }

    public function isInQuery(): bool
    {
        return $this->in === self::IN_QUERY;
    }

    public static function isKnownLocation(string $location): bool
    {
        return in_array($location, self::LOCATIONS, true);
    }
}
