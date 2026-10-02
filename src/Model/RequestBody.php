<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Model;

/**
 * Describes the payload a request accepts.
 */
final class RequestBody
{
    public const TYPE_JSON = 'json';
    public const TYPE_FORM = 'form';
    public const TYPE_MULTIPART = 'multipart';
    public const TYPE_TEXT = 'text';

    /**
     * @var string One of the TYPE_* constants.
     */
    public $type = self::TYPE_JSON;

    /**
     * @var string
     */
    public $description = '';

    /**
     * @var bool
     */
    public $required = false;

    /**
     * @var mixed Decoded example payload.
     */
    public $example;

    /**
     * @var bool
     */
    public $hasExample = false;

    /**
     * Field level documentation, mainly useful for form data.
     *
     * @var list<Parameter>
     */
    public $properties = [];

    public function addProperty(Parameter $property): void
    {
        $this->properties[] = $property;
    }

    public function hasProperties(): bool
    {
        return $this->properties !== [];
    }

    public function contentType(): string
    {
        switch ($this->type) {
            case self::TYPE_FORM:
                return 'application/x-www-form-urlencoded';
            case self::TYPE_MULTIPART:
                return 'multipart/form-data';
            case self::TYPE_TEXT:
                return 'text/plain';
            case self::TYPE_JSON:
            default:
                return 'application/json';
        }
    }
}
