<?php

/**
 * Каталог сравниваемых пакетов (≥5 аналогов + наш). Источник — comparison-packages.json.
 */

declare(strict_types=1);

require_once __DIR__ . '/ComparisonCatalogException.php';

use CloudCastle\ConnectManager\Tools\ComparisonCatalogException;

$raw = file_get_contents(__DIR__ . '/comparison-packages.json');

if ($raw === false) {
    throw new ComparisonCatalogException('Не удалось прочитать comparison-packages.json');
}

$data = json_decode($raw, true);

if (!is_array($data)) {
    throw new ComparisonCatalogException('Некорректный comparison-packages.json');
}

return $data;
