<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Parser;

/**
 * A method found in a source file, together with its docblock.
 */
final class ParsedMethod
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var string|null Raw docblock text, null when the method has none.
     */
    public $docBlock;

    /**
     * @var int Line the docblock starts on, or the "function" keyword line when
     *          there is no docblock.
     */
    public $line;

    /**
     * @var string public, protected or private.
     */
    public $visibility = 'public';

    /**
     * @var bool
     */
    public $isStatic = false;

    /**
     * @var bool
     */
    public $isAbstract = false;

    public function __construct(string $name, int $line = 0)
    {
        $this->name = $name;
        $this->line = $line;
    }

    public function hasDocBlock(): bool
    {
        return $this->docBlock !== null && trim($this->docBlock) !== '';
    }

    public function isPublic(): bool
    {
        return $this->visibility === 'public';
    }
}
