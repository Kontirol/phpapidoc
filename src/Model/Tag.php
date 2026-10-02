<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Model;

/**
 * A documentation group, rendered as an OpenAPI tag.
 */
final class Tag
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $description = '';

    public function __construct(string $name, string $description = '')
    {
        $this->name = $name;
        $this->description = $description;
    }
}
