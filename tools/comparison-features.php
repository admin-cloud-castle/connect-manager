<?php

/**
 * Матрица фич. Источник — comparison-features.json.
 */

declare(strict_types=1);

$raw = file_get_contents(__DIR__ . '/comparison-features.json');

if ($raw === false) {
    throw new RuntimeException('Не удалось прочитать comparison-features.json');
}

$data = json_decode($raw, true);

if (!is_array($data)) {
    throw new RuntimeException('Некорректный comparison-features.json');
}

return $data;
