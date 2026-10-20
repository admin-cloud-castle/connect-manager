<?php

/**
 * Матрица фич для сравнения с аналогами (≥5 конкурентов).
 *
 * @return list<array{feature: string, packages: array<string, bool>}>
 */

declare(strict_types=1);

$packages = [
    'ours' => 'cloud-castle/connect-manager',
    'react/socket' => 'react/socket',
    'amphp/socket' => 'amphp/socket',
    'guzzlehttp/guzzle' => 'guzzlehttp/guzzle',
    'phpseclib/phpseclib' => 'phpseclib/phpseclib',
    'clue/socket-raw' => 'clue/socket-raw',
];

$yes = static function (array $flags) use ($packages): array {
    /** @var array<string, bool> $out */
    $out = [];
    foreach (array_keys($packages) as $key) {
        $out[$key] = (bool) ($flags[$key] ?? false);
    }

    return $out;
};

return [
    [
        'feature' => 'PHP 8.1+',
        'packages' => $yes([
            'ours' => true,
            'react/socket' => true,
            'amphp/socket' => true,
            'guzzlehttp/guzzle' => true,
            'phpseclib/phpseclib' => true,
            'clue/socket-raw' => true,
        ]),
    ],
    [
        'feature' => 'Единый Connection API для любого протокола',
        'packages' => $yes(['ours' => true]),
    ],
    [
        'feature' => 'Реестр протоколов OSI (100+)',
        'packages' => $yes(['ours' => true]),
    ],
    [
        'feature' => 'TCP / UDP / Unix / TLS нативные stream',
        'packages' => $yes([
            'ours' => true,
            'react/socket' => true,
            'amphp/socket' => true,
            'clue/socket-raw' => true,
        ]),
    ],
    [
        'feature' => 'HTTP(S) как транспортный клиент',
        'packages' => $yes(['ours' => true, 'guzzlehttp/guzzle' => true]),
    ],
    [
        'feature' => 'SSH / SFTP прикладной стек',
        'packages' => $yes(['ours' => true, 'phpseclib/phpseclib' => true]),
    ],
    [
        'feature' => 'Логические соединения для L1/L2 без native PHP',
        'packages' => $yes(['ours' => true]),
    ],
    [
        'feature' => 'Sync режим (обычное PHP-приложение)',
        'packages' => $yes([
            'ours' => true,
            'guzzlehttp/guzzle' => true,
            'phpseclib/phpseclib' => true,
            'clue/socket-raw' => true,
        ]),
    ],
    [
        'feature' => 'Async режим (Fibers / event-loop)',
        'packages' => $yes(['ours' => true, 'react/socket' => true, 'amphp/socket' => true]),
    ],
    [
        'feature' => 'Async без внешних event-loop зависимостей (Fibers)',
        'packages' => $yes(['ours' => true]),
    ],
    [
        'feature' => 'Пул соединений',
        'packages' => $yes(['ours' => true, 'guzzlehttp/guzzle' => true]),
    ],
    [
        'feature' => 'SSRF-guard (блок частных адресов)',
        'packages' => $yes(['ours' => true]),
    ],
    [
        'feature' => 'TLS verify peer по умолчанию',
        'packages' => $yes([
            'ours' => true,
            'guzzlehttp/guzzle' => true,
            'react/socket' => true,
            'amphp/socket' => true,
        ]),
    ],
    [
        'feature' => 'Генераторы чтения (экономия памяти)',
        'packages' => $yes(['ours' => true]),
    ],
    [
        'feature' => 'Пользовательская регистрация протоколов',
        'packages' => $yes(['ours' => true]),
    ],
    [
        'feature' => 'Ноль runtime-зависимостей Composer',
        'packages' => $yes(['ours' => true, 'clue/socket-raw' => true]),
    ],
    [
        'feature' => 'Raw sockets (ext-sockets)',
        'packages' => $yes(['ours' => true, 'clue/socket-raw' => true]),
    ],
    [
        'feature' => 'Только HTTP-ориентированный API',
        'packages' => $yes(['guzzlehttp/guzzle' => true]),
    ],
    [
        'feature' => 'Только ReactPHP event-loop',
        'packages' => $yes(['react/socket' => true]),
    ],
    [
        'feature' => 'Только Amp event-loop',
        'packages' => $yes(['amphp/socket' => true]),
    ],
];
