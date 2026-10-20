<?php

/**
 * Матрица security. Источник — comparison-security.json.
 */

declare(strict_types=1);

$raw = file_get_contents(__DIR__ . '/comparison-security.json');

if ($raw === false) {
    throw new RuntimeException('Не удалось прочитать comparison-security.json');
}

$data = json_decode($raw, true);

if (!is_array($data)) {
    throw new RuntimeException('Некорректный comparison-security.json');
}

return $data;
