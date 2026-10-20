<?php

/**
 * Генератор порогов (THRESHOLDS-маркеры).
 */

declare(strict_types=1);

$raw = file_get_contents(__DIR__ . '/../infection.json.dist');
$decoded = is_string($raw) ? json_decode($raw, true) : null;
$minMsi = 100;

if (is_array($decoded) && isset($decoded['minMsi']) && is_numeric($decoded['minMsi'])) {
    $minMsi = (int) $decoded['minMsi'];
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
