<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Support;

use CloudCastle\ConnectManager\Tools\ComparisonLoader;

/**
 * Доступ к каталогу аналогов для comparative-тестов.
 */
final class ComparisonCatalog
{
    /**
     * @return array{
     *     order: list<string>,
     *     labels: array<string, string>,
     *     composer: array<string, string>,
     *     classes: array<string, string>
     * }
     */
    public static function load(): array
    {
        return ComparisonLoader::packages();
    }

    /**
     * Ключи конкурентов без нашего пакета.
     *
     * @return list<string>
     */
    public static function competitorKeys(): array
    {
        $keys = [];

        foreach (self::load()['order'] as $key) {
            if ($key !== 'ours') {
                $keys[] = $key;
            }
        }

        return $keys;
    }
}
