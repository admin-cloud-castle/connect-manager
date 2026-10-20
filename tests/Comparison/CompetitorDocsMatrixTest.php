<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Comparison;

use CloudCastle\ConnectManager\Tests\Support\ComparisonCatalog;
use CloudCastle\ConnectManager\Tools\ComparisonLoader;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Авто-матрицы фич/безопасности покрывают всех аналогов; wiki-блоки не пустые.
 *
 * @SuppressWarnings("PHPMD.StaticAccess")
 */
#[Group('comparison')]
#[CoversNothing]
final class CompetitorDocsMatrixTest extends TestCase
{
    /**
     * features matrix содержит ключ каждого пакета.
     */
    public function testFeaturesMatrixCoversAllPackages(): void
    {
        $matrix = ComparisonLoader::features();
        self::assertGreaterThanOrEqual(15, count($matrix));

        foreach (ComparisonCatalog::load()['order'] as $key) {
            foreach ($matrix as $row) {
                self::assertArrayHasKey($key, $row['packages'], $row['feature']);
            }
        }
    }

    /**
     * security matrix содержит ключ каждого пакета.
     */
    public function testSecurityMatrixCoversAllPackages(): void
    {
        $matrix = ComparisonLoader::security();
        self::assertGreaterThanOrEqual(8, count($matrix));

        foreach (ComparisonCatalog::load()['order'] as $key) {
            foreach ($matrix as $row) {
                self::assertArrayHasKey($key, $row['packages'], $row['standard']);
            }
        }
    }

    /**
     * wiki/docs/ru/Comparison.md содержит заполненные FEATURES/BENCH/SECURITY.
     */
    public function testRussianComparisonWikiBlocksFilled(): void
    {
        $path = dirname(__DIR__, 2) . '/wiki/docs/ru/Comparison.md';
        self::assertFileExists($path);
        $content = file_get_contents($path);
        self::assertNotFalse($content);

        foreach (['FEATURES', 'BENCH', 'SECURITY'] as $block) {
            self::assertMatchesRegularExpression(
                '/<!-- ' . $block . ':START -->\s*\n\|.+\|/s',
                $content,
                $block . ' block must contain a markdown table',
            );
        }

        self::assertStringContainsString('react/socket', $content);
        self::assertStringContainsString('amphp/socket', $content);
        self::assertStringContainsString('guzzle', $content);
        self::assertStringContainsString('phpseclib', $content);
        self::assertStringContainsString('socket-raw', $content);
        self::assertStringContainsString('SSRF-guard', $content);
    }
}
