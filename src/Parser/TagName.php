<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Parser;

/**
 * Every annotation name the parser knows about.
 *
 * Keeping them in one place avoids typo driven bugs and gives the docs a single
 * source of truth.
 */
final class TagName
{
    public const NAME = 'name';
    public const DESC = 'desc';
    public const ROUTE = 'route';
    public const METHOD = 'method';
    public const PARAM = 'param';
    public const BODY = 'body';
    public const BODY_PARAM = 'bodyParam';
    public const RESPONSE = 'response';
    public const GROUP = 'group';
    public const TAG = 'tag';
    public const AUTH = 'auth';
    public const IGNORE = 'ignore';
    public const DEPRECATED = 'deprecated';
    public const EXAMPLE = 'example';

    /**
     * Tags that carry meaning for the generator. Unknown tags are ignored.
     *
     * @var list<string>
     */
    public const KNOWN = [
        self::NAME,
        self::DESC,
        self::ROUTE,
        self::METHOD,
        self::PARAM,
        self::BODY,
        self::BODY_PARAM,
        self::RESPONSE,
        self::GROUP,
        self::TAG,
        self::AUTH,
        self::IGNORE,
        self::DEPRECATED,
        self::EXAMPLE,
    ];

    private function __construct()
    {
    }
}
