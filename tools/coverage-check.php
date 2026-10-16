<?php

declare(strict_types=1);

/**
 * Проверяет порог покрытия по clover.xml (общий и per-file).
 */

const MIN_LINE_COVERAGE_PERCENT = 99.8;

$cloverPath = $argv[1] ?? 'var/coverage/clover.xml';

if (!is_file($cloverPath)) {
    fwrite(STDERR, "Файл покрытия не найден: {$cloverPath}\n");
    exit(1);
}

$xml = new SimpleXMLElement((string) file_get_contents($cloverPath));
$metrics = $xml->project->metrics ?? $xml->metrics ?? null;

if ($metrics === null) {
    fwrite(STDERR, "Не удалось прочитать metrics из clover\n");
    exit(1);
}

$statements = (int) $metrics['statements'];
$covered = (int) $metrics['coveredstatements'];
$percent = $statements > 0 ? ($covered / $statements) * 100 : 100.0;

if ($percent + 0.0001 < MIN_LINE_COVERAGE_PERCENT) {
    fwrite(STDERR, sprintf("Покрытие %.2f%% ниже порога %.1f%%\n", $percent, MIN_LINE_COVERAGE_PERCENT));
    exit(1);
}

foreach ($xml->xpath('//file') ?: [] as $file) {
    $fileMetrics = $file->metrics;
    $fileStatements = (int) $fileMetrics['statements'];
    $fileCovered = (int) $fileMetrics['coveredstatements'];

    if ($fileStatements === 0) {
        continue;
    }

    $filePercent = ($fileCovered / $fileStatements) * 100;

    if ($filePercent + 0.0001 < MIN_LINE_COVERAGE_PERCENT) {
        $name = (string) $file['name'];
        fwrite(STDERR, sprintf("Файл %s: %.2f%% ниже порога %.1f%%\n", $name, $filePercent, MIN_LINE_COVERAGE_PERCENT));
        exit(1);
    }
}

fwrite(STDOUT, sprintf("Покрытие OK: %.2f%% (порог %.1f%%)\n", $percent, MIN_LINE_COVERAGE_PERCENT));
