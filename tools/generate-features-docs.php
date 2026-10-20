<?php

/**
 * Генератор матрицы фич (FEATURES-маркеры) во wiki и .docs.
 */

declare(strict_types=1);

/** @var list<array{feature: string, packages: array<string, bool>}> $matrix */
$matrix = require __DIR__ . '/comparison-features.php';

$packageOrder = [
    'ours',
    'react/socket',
    'amphp/socket',
    'guzzlehttp/guzzle',
    'phpseclib/phpseclib',
    'clue/socket-raw',
];

$headers = [
    'Фича',
    'Connect Manager',
    'react/socket',
    'amphp/socket',
    'guzzle',
    'phpseclib',
    'socket-raw',
    '🏆 Победитель',
];

$lines = [];
$lines[] = '| ' . implode(' | ', $headers) . ' |';
$lines[] = '| ' . implode(' | ', array_fill(0, count($headers), '---')) . ' |';

foreach ($matrix as $row) {
    $cells = [$row['feature']];
    /** @var list<string> $winners */
    $winners = [];

    foreach ($packageOrder as $key) {
        $has = $row['packages'][$key] ?? false;
        $cells[] = $has ? '✅' : '❌';

        if ($has) {
            $winners[] = $key === 'ours' ? 'Connect Manager' : $key;
        }
    }

    if (($row['packages']['ours'] ?? false) === true) {
        $winner = 'Connect Manager';
    } elseif ($winners === []) {
        $winner = '—';
    } else {
        $winner = implode(', ', $winners);
    }

    $cells[] = $winner;
    $lines[] = '| ' . implode(' | ', $cells) . ' |';
}

$table = implode("\n", $lines) . "\n";

$targets = [
    'wiki/docs/ru/Comparison.md',
    'wiki/docs/en/Comparison.md',
    '.docs/en/README.md',
];

foreach ($targets as $target) {
    if (!is_file($target)) {
        continue;
    }

    $content = file_get_contents($target);

    if ($content === false) {
        continue;
    }

    if (!str_contains($content, '<!-- FEATURES:START -->')) {
        $content .= "\n\n<!-- FEATURES:START -->\n" . $table . "<!-- FEATURES:END -->\n";
    } else {
        $replaced = preg_replace(
            '/<!-- FEATURES:START -->.*?<!-- FEATURES:END -->/s',
            "<!-- FEATURES:START -->\n" . $table . '<!-- FEATURES:END -->',
            $content,
        );
        $content = is_string($replaced) ? $replaced : $content;
    }

    file_put_contents($target, $content);
    fwrite(STDOUT, "updated {$target}\n");
}

fwrite(STDOUT, "generate-features-docs: OK\n");
