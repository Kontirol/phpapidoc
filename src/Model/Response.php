<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Model;

/**
 * A single documented response.
 */
final class Response
{
    /**
     * @var string HTTP status code, "200", "404", "default", ...
     */
    public $statusCode = '200';

    /**
     * @var string
     */
    public $description = '';

    /**
     * @var mixed Decoded example payload (array, scalar or null).
     */
    public $example;

    /**
     * @var bool
     */
    public $hasExample = false;

    /**
     * @var string
     */
    public $contentType = 'application/json';

    public function __construct(string $statusCode = '200', string $description = '')
    {
        $this->statusCode = $statusCode;
        $this->description = $description;
    }

    public function isSuccess(): bool
    {
        return $this->statusCode !== '' && $this->statusCode[0] === '2';
    }
}
