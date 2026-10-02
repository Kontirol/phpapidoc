<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Scanner;

/**
 * A file candidate for parsing, plus the context it was discovered in.
 */
final class ControllerFile
{
    /**
     * @var string Absolute file path.
     */
    public $path;

    /**
     * @var string Absolute directory the file was discovered under.
     */
    public $root;

    /**
     * @var string Path relative to $root, using forward slashes.
     */
    public $relativePath;

    /**
     * @var string Route prefix declared for this controller source.
     */
    public $prefix;

    /**
     * @var string|null Namespace declared for this source, used only when the
     *                    file itself carries no namespace statement.
     */
    public $namespace;

    public function __construct(
        string $path,
        string $root,
        string $relativePath,
        string $prefix = '',
        ?string $namespace = null
    ) {
        $this->path = $path;
        $this->root = $root;
        $this->relativePath = $relativePath;
        $this->prefix = $prefix;
        $this->namespace = $namespace;
    }
}
