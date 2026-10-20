<?php

/**
 * Каталог сравниваемых пакетов (≥5 аналогов + наш). Источник — comparison-packages.json.
 */

declare(strict_types=1);

$raw = file_get_contents(__DIR__ . '/comparison-packages.json');

if ($raw === false) {
    throw new RuntimeException('Не удалось прочитать comparison-packages.json');
}

$data = json_decode($raw, true);

if (!is_array($data)) {
    throw new RuntimeException('Некорректный comparison-packages.json');
}

return $data;
