<?php

declare(strict_types=1);

namespace Kontirol\ApiDoc\Parser;

/**
 * Turns the raw text of a docblock into a list of tags.
 *
 * The parser is deliberately dumb: it does not know what "@param" means, it only
 * splits the comment into "@name value" pairs and keeps track of line numbers.
 * Interpreting the tags is the job of EndpointBuilder.
 */
final class DocBlockParser
{
    /**
     * @param string $docBlock  Raw docblock text, including the /** and * / markers.
     * @param int    $startLine Line number the docblock starts on, used for diagnostics.
     */
    public function parse(string $docBlock, int $startLine = 1): ParsedDocBlock
    {
        $result = new ParsedDocBlock();
        $current = null;

        foreach ($this->splitLines($docBlock) as $offset => $line) {
            $lineNumber = $startLine + $offset;

            if ($line === '') {
                continue;
            }

            if ($line[0] === '@') {
                if ($current !== null) {
                    $result->add($current);
                }

                $current = $this->createTag($line, $lineNumber);

                continue;
            }

            // Continuation of the previous tag value.
            if ($current !== null) {
                $current->append($line);
            }
        }

        if ($current !== null) {
            $result->add($current);
        }

        return $result;
    }

    /**
     * Strips the comment markers and the leading asterisk of every line.
     *
     * @return list<string>
     */
    private function splitLines(string $docBlock): array
    {
        // Splitting on \R would be wrong: in byte mode \R also matches the
        // single byte 0x85, which is the tail of plenty of UTF-8 Chinese
        // characters (e.g. "情" = E6 83 85). Splitting on explicit line
        // breaks keeps multi byte characters intact.
        $rawLines = preg_split("/\r\n|\r|\n/", $docBlock);

        if ($rawLines === false) {
            return [];
        }

        $lines = [];

        foreach ($rawLines as $rawLine) {
            $lines[] = $this->cleanLine($rawLine);
        }

        return $lines;
    }

    private function cleanLine(string $line): string
    {
        $line = trim($line);

        if ($line === '' || $line === '/**' || $line === '/*' || $line === '*/') {
            return '';
        }

        // Drop a leading /** or /* and a trailing */.
        $line = trim((string) preg_replace('#^/\*+#', '', $line));
        $line = trim((string) preg_replace('#\*+/$#', '', $line));

        // Drop the leading asterisk of a continued docblock line.
        $line = (string) preg_replace('#^\*+\s?#', '', $line);

        return trim($line);
    }

    private function createTag(string $line, int $lineNumber): TagValue
    {
        $body = substr($line, 1);

        if (preg_match('#^([A-Za-z0-9_\\\\\-]+)\s*(.*)$#s', $body, $matches) === 1) {
            return new TagValue($matches[1], trim($matches[2]), $lineNumber, $line);
        }

        return new TagValue($body, '', $lineNumber, $line);
    }
}
