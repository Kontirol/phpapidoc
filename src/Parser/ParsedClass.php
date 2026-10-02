<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Parser;

/**
 * A class (or interface / trait) found in a source file.
 */
final class ParsedClass
{
    /**
     * @var string Short class name.
     */
    public $name;

    /**
     * @var string Namespace without leading or trailing backslashes, "" for the global namespace.
     */
    public $namespace;

    /**
     * @var int Line of the class keyword.
     */
    public $line;

    /**
     * @var string|null Class level docblock, kept for reference only. It is never
     *                     turned into an endpoint.
     */
    public $docBlock;

    /**
     * @var list<ParsedMethod>
     */
    public $methods = [];

    public function __construct(string $name, string $namespace = '', int $line = 0)
    {
        $this->name = $name;
        $this->namespace = trim($namespace, '\\');
        $this->line = $line;
    }

    public function fqcn(): string
    {
        return $this->namespace === '' ? $this->name : $this->namespace . '\\' . $this->name;
    }

    public function addMethod(ParsedMethod $method): void
    {
        $this->methods[] = $method;
    }

    /**
     * Methods that can actually answer an HTTP request.
     *
     * @return list<ParsedMethod>
     */
    public function publicMethods(): array
    {
        $result = [];

        foreach ($this->methods as $method) {
            if ($method->isPublic() && !$method->isAbstract) {
                $result[] = $method;
            }
        }

        return $result;
    }
}
