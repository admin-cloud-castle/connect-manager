<?php

/**
 * Матрица security. Источник — comparison-security.json.
 */

declare(strict_types=1);

require_once __DIR__ . '/ComparisonCatalogException.php';

use CloudCastle\ConnectManager\Tools\ComparisonCatalogException;

$raw = file_get_contents(__DIR__ . '/comparison-security.json');

if ($raw === false) {
    throw new ComparisonCatalogException('Не удалось прочитать comparison-security.json');
}

$data = json_decode($raw, true);

if (!is_array($data)) {
    throw new ComparisonCatalogException('Некорректный comparison-security.json');
}

return $data;
