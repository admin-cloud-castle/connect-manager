<?php

/**
 * Генератор матрицы фич (FEATURES-маркеры) во все языковые Comparison.md.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use CloudCastle\ConnectManager\Tools\ComparisonLoader;

$root = dirname(__DIR__);
$matrix = ComparisonLoader::features();
$packages = ComparisonLoader::packages();
$packageOrder = $packages['order'];
$labels = $packages['labels'];

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
            $winners[] = $key === 'ours' ? 'Connect Manager' : ($labels[$key] ?? $key);
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
    'wiki/docs/de/Comparison.md',
    'wiki/docs/fr/Comparison.md',
    'wiki/docs/es/Comparison.md',
    'wiki/docs/it/Comparison.md',
];

foreach ($targets as $rel) {
    $target = $root . '/' . $rel;

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
    fwrite(STDOUT, "updated {$rel}\n");
}

fwrite(STDOUT, "generate-features-docs: OK\n");
