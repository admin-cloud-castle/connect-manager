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

$descriptors = [
    0 => STDIN,
    1 => STDOUT,
    2 => STDERR,
];

// argv-массив без shell — безопасно; Semgrep flags любой proc_open.
// nosemgrep: php.lang.security.exec-use.exec-use
$process = proc_open($cmd, $descriptors, $pipes);
if (!is_resource($process)) {
    fwrite(STDERR, "Не удалось запустить Infection\n");
    exit(1);
}

exit(proc_close($process));
