<?php

declare(strict_types=1);

$spec = json_decode((string) file_get_contents(__DIR__ . '/build/guahao-scan.json'), true);

if (!is_array($spec)) {
    fwrite(STDERR, "cannot read the generated spec\n");

    exit(1);
}

echo 'paths: ' . count($spec['paths']) . PHP_EOL . PHP_EOL;

foreach ($spec['paths'] as $path => $operations) {
    foreach (array_keys($operations) as $method) {
        printf('%-7s %s%s', strtoupper($method), $path, PHP_EOL);
    }
}
