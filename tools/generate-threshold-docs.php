<?php

/**
 * Генератор порогов (THRESHOLDS-маркеры).
 */

declare(strict_types=1);

$raw = file_get_contents(__DIR__ . '/../infection.json.dist');
$minMsi = 100;

if (is_string($raw) && preg_match('/"minMsi"\s*:\s*(\d+)/', $raw, $matches) === 1) {
    $minMsi = (int) $matches[1];
}

$coverageMin = 99.8;

$table = <<<MD
    | Метрика | Порог | 🏆 Победитель |
    | --- | --- | --- |
    | Покрытие строк (per-file) | ≥ {$coverageMin}% | Connect Manager |
    | Infection MSI | {$minMsi}% | Connect Manager |
    | PHPStan | level max | Connect Manager |
    | Psalm | errorLevel 1 | Connect Manager |
    | PHPCS | PSR-12 | Connect Manager |

    MD;

$targets = [
    'wiki/docs/ru/Testing.md',
    'wiki/docs/en/Testing.md',
    'wiki/docs/ru/Comparison.md',
    'wiki/docs/en/Comparison.md',
    'wiki/docs/de/Comparison.md',
    'wiki/docs/fr/Comparison.md',
    'wiki/docs/es/Comparison.md',
    'wiki/docs/it/Comparison.md',
];

foreach ($targets as $target) {
    if (!is_file($target)) {
        continue;
    }

    $content = file_get_contents($target);

    if ($content === false) {
        continue;
    }

    if (!str_contains($content, '<!-- THRESHOLDS:START -->')) {
        $content .= "\n\n<!-- THRESHOLDS:START -->\n" . $table . "<!-- THRESHOLDS:END -->\n";
    } else {
        $replaced = preg_replace(
            '/<!-- THRESHOLDS:START -->.*?<!-- THRESHOLDS:END -->/s',
            "<!-- THRESHOLDS:START -->\n" . $table . '<!-- THRESHOLDS:END -->',
            $content,
        );
        $content = is_string($replaced) ? $replaced : $content;
    }

    file_put_contents($target, $content);
    fwrite(STDOUT, "updated {$target}\n");
}

fwrite(STDOUT, "generate-threshold-docs: OK\n");
