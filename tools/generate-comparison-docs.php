<?php

/**
 * Пишет BENCH и SECURITY таблицы во все языковые Comparison.md.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CloudCastle\ConnectManager\Tools\ComparisonLoader;

$root = dirname(__DIR__);
$packages = ComparisonLoader::packages();
$securityMatrix = ComparisonLoader::security();
$labels = $packages['labels'];
$productName = 'Connect Manager';

$avgPath = $root . '/var/benchmark/comparison-avg.json';

if (!is_file($avgPath)) {
    fwrite(STDERR, "нет {$avgPath} — сначала: composer docs:comparison\n");
    exit(1);
}

$avgRaw = file_get_contents($avgPath);
$avg = is_string($avgRaw) ? json_decode($avgRaw, true) : null;

if (!is_array($avg) || !isset($avg['benches']) || !is_array($avg['benches'])) {
    fwrite(STDERR, "invalid comparison-avg.json\n");
    exit(1);
}

$benches = [];

foreach ($avg['benches'] as $benchName => $benchRow) {
    if (!is_string($benchName) || !is_array($benchRow)) {
        continue;
    }

    $nsPerOp = 0.0;
    $memoryDelta = 0;

    if (array_key_exists('ns_per_op', $benchRow)
        && (is_int($benchRow['ns_per_op']) || is_float($benchRow['ns_per_op']))
    ) {
        $nsPerOp = (float) $benchRow['ns_per_op'];
    }

    if (array_key_exists('memory_delta', $benchRow) && is_int($benchRow['memory_delta'])) {
        $memoryDelta = $benchRow['memory_delta'];
    } elseif (array_key_exists('memory_delta', $benchRow) && is_float($benchRow['memory_delta'])) {
        $memoryDelta = (int) $benchRow['memory_delta'];
    }

    $benches[$benchName] = [
        'ns_per_op' => $nsPerOp,
        'memory_delta' => $memoryDelta,
    ];
}

$phpVersions = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;

if (isset($avg['php_versions']) && is_array($avg['php_versions'])) {
    $versionParts = [];

    foreach (array_keys($avg['php_versions']) as $versionKey) {
        if (!isset($avg['php_versions'][$versionKey])) {
            continue;
        }

        if (is_string($avg['php_versions'][$versionKey])) {
            $versionParts[] = $avg['php_versions'][$versionKey];
        } elseif (is_int($avg['php_versions'][$versionKey]) || is_float($avg['php_versions'][$versionKey])) {
            $versionParts[] = (string) $avg['php_versions'][$versionKey];
        }
    }

    if ($versionParts !== []) {
        $phpVersions = implode(', ', $versionParts);
    }
}

$fmtNs = static function (float $ns): string {
    if ($ns >= 1_000_000.0) {
        return sprintf('%.2f ms', $ns / 1_000_000.0);
    }

    if ($ns >= 1000.0) {
        return sprintf('%.1f µs', $ns / 1000.0);
    }

    return sprintf('%.0f ns', $ns);
};

$fmtMem = static function (int $bytes): string {
    if (abs($bytes) >= 1024 * 1024) {
        return sprintf('%.2f MiB', $bytes / (1024 * 1024));
    }

    if (abs($bytes) >= 1024) {
        return sprintf('%.1f KiB', $bytes / 1024);
    }

    return $bytes . ' B';
};

$nsOf = static function (string $key) use ($benches): float {
    return $benches[$key]['ns_per_op'] ?? 0.0;
};

$memOf = static function (string $key) use ($benches): int {
    return $benches[$key]['memory_delta'] ?? 0;
};

$constructNs = [
    'ours' => $nsOf('ours_logical_connect'),
    'react/socket' => $nsOf('react_connector_construct'),
    'amphp/socket' => $nsOf('amphp_connector_construct'),
    'guzzlehttp/guzzle' => $nsOf('guzzle_client_construct'),
    'phpseclib/phpseclib' => $nsOf('phpseclib_class_exists'),
    'clue/socket-raw' => $nsOf('socket_raw_factory_construct'),
];

$positiveNs = array_values(array_filter($constructNs, static fn (float $v): bool => $v > 0));
$minConstruct = $positiveNs === [] ? 0.0 : min($positiveNs);
$constructWinner = '—';

foreach ($constructNs as $key => $value) {
    if ($value > 0 && abs($value - $minConstruct) < 0.0001) {
        $constructWinner = $key === 'ours' ? $productName : ($labels[$key] ?? $key);
        break;
    }
}

/** @var list<list<string>> $benchRows */
$benchRows = [
    ['Метрика', $productName, 'react/socket', 'amphp/socket', 'guzzle', 'phpseclib', 'socket-raw', '🏆 Победитель'],
    [
        'Конструкция клиента / connect (ns/op, avg PHP ' . $phpVersions . ')',
        $fmtNs($constructNs['ours']),
        $fmtNs($constructNs['react/socket']),
        $fmtNs($constructNs['amphp/socket']),
        $fmtNs($constructNs['guzzlehttp/guzzle']),
        $fmtNs($constructNs['phpseclib/phpseclib']),
        $fmtNs($constructNs['clue/socket-raw']),
        $constructWinner,
    ],
    [
        'Registry lookup tcp (ns/op)',
        $fmtNs($nsOf('ours_registry_lookup')),
        '—',
        '—',
        '—',
        '—',
        '—',
        $productName,
    ],
];

$memDeltas = [
    'ours' => $memOf('ours_logical_connect'),
    'react/socket' => $memOf('react_connector_construct'),
    'amphp/socket' => $memOf('amphp_connector_construct'),
    'guzzlehttp/guzzle' => $memOf('guzzle_client_construct'),
    'phpseclib/phpseclib' => $memOf('phpseclib_class_exists'),
    'clue/socket-raw' => $memOf('socket_raw_factory_construct'),
];

$positiveMem = array_values(array_filter($memDeltas, static fn (int $v): bool => $v >= 0));
$minMem = $positiveMem === [] ? 0 : min($positiveMem);
$memWinner = '—';

foreach ($memDeltas as $key => $value) {
    if ($value === $minMem) {
        $memWinner = $key === 'ours' ? $productName : ($labels[$key] ?? $key);
        break;
    }
}

$benchRows[] = [
    'Δ памяти на прогон конструкций',
    $fmtMem($memDeltas['ours']),
    $fmtMem($memDeltas['react/socket']),
    $fmtMem($memDeltas['amphp/socket']),
    $fmtMem($memDeltas['guzzlehttp/guzzle']),
    $fmtMem($memDeltas['phpseclib/phpseclib']),
    $fmtMem($memDeltas['clue/socket-raw']),
    $memWinner,
];

$toTable = static function (array $rows): string {
    /** @var list<list<string>> $rows */
    $lines = [];
    $header = $rows[0];
    $lines[] = '| ' . implode(' | ', $header) . ' |';
    $lines[] = '| ' . implode(' | ', array_fill(0, count($header), '---')) . ' |';

    foreach (array_slice($rows, 1) as $row) {
        $lines[] = '| ' . implode(' | ', $row) . ' |';
    }

    return implode("\n", $lines) . "\n";
};

$benchTable = $toTable($benchRows);

$secHeaders = [
    'Стандарт / практика',
    $productName,
    'react/socket',
    'amphp/socket',
    'guzzle',
    'phpseclib',
    'socket-raw',
    '🏆 Победитель',
];
$secLines = [];
$secLines[] = '| ' . implode(' | ', $secHeaders) . ' |';
$secLines[] = '| ' . implode(' | ', array_fill(0, count($secHeaders), '---')) . ' |';

foreach ($securityMatrix as $row) {
    $cells = [$row['standard']];
    $winners = [];

    foreach ($packages['order'] as $key) {
        $has = $row['packages'][$key] ?? false;
        $cells[] = $has ? '✅' : '❌';

        if ($has) {
            $winners[] = $key === 'ours' ? $productName : ($labels[$key] ?? $key);
        }
    }

    if (($row['packages']['ours'] ?? false) === true) {
        $winner = $productName;
    } elseif ($winners === []) {
        $winner = '—';
    } else {
        $winner = implode(', ', $winners);
    }

    $cells[] = $winner;
    $secLines[] = '| ' . implode(' | ', $cells) . ' |';
}

$securityTable = implode("\n", $secLines) . "\n";

$replaceBlock = static function (string $content, string $start, string $end, string $body): string {
    if (!str_contains($content, $start)) {
        return $content . "\n\n{$start}\n{$body}{$end}\n";
    }

    $replaced = preg_replace(
        '/' . preg_quote($start, '/') . '.*?' . preg_quote($end, '/') . '/s',
        $start . "\n" . $body . $end,
        $content,
    );

    return is_string($replaced) ? $replaced : $content;
};

$targets = [
    'wiki/docs/ru/Comparison.md',
    'wiki/docs/en/Comparison.md',
    'wiki/docs/de/Comparison.md',
    'wiki/docs/fr/Comparison.md',
    'wiki/docs/es/Comparison.md',
    'wiki/docs/it/Comparison.md',
];

foreach ($targets as $rel) {
    $path = $root . '/' . $rel;

    if (!is_file($path)) {
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0o775, true);
        }

        $stub = "# Comparison\n\n"
            . "<!-- FEATURES:START -->\n<!-- FEATURES:END -->\n\n"
            . "<!-- BENCH:START -->\n<!-- BENCH:END -->\n\n"
            . "<!-- SECURITY:START -->\n<!-- SECURITY:END -->\n";
        file_put_contents($path, $stub);
    }

    $content = file_get_contents($path);

    if ($content === false) {
        continue;
    }

    $content = $replaceBlock($content, '<!-- BENCH:START -->', '<!-- BENCH:END -->', $benchTable);
    $content = $replaceBlock($content, '<!-- SECURITY:START -->', '<!-- SECURITY:END -->', $securityTable);
    file_put_contents($path, $content);
    fwrite(STDOUT, "updated {$rel}\n");
}

fwrite(STDOUT, "generate-comparison-docs: OK\n");
