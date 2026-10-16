<?php

declare(strict_types=1);

/**
 * Запуск Infection с повышенным memory_limit.
 */

$cmd = [
    PHP_BINARY,
    '-d',
    'memory_limit=3G',
    dirname(__DIR__) . '/vendor/bin/infection',
    '--configuration=infection.json.dist',
    '--threads=max',
    '--show-mutations',
];

passthru(implode(' ', array_map('escapeshellarg', $cmd)), $code);
exit($code);
