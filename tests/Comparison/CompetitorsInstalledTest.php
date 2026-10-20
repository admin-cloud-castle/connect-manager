<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Comparison;

use CloudCastle\ConnectManager\Tests\Support\ComparisonCatalog;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Все ≥5 аналогов установлены как require-dev и не попадают в runtime require.
 *
 * @SuppressWarnings("PHPMD.StaticAccess")
 */
#[Group('comparison')]
#[CoversNothing]
final class CompetitorsInstalledTest extends TestCase
{
    /**
     * Каталог содержит ровно 5 конкурентов + ours.
     */
    public function testCatalogHasFiveCompetitors(): void
    {
        $competitors = ComparisonCatalog::competitorKeys();
        self::assertCount(5, $competitors);
        self::assertSame(
            [
                'react/socket',
                'amphp/socket',
                'guzzlehttp/guzzle',
                'phpseclib/phpseclib',
                'clue/socket-raw',
            ],
            $competitors,
        );
    }

    /**
     * Пакет конкурента есть в require-dev и отсутствует в require.
     *
     * @param string $key Ключ каталога
     */
    #[DataProvider('competitorProvider')]
    public function testCompetitorIsDevOnlyDependency(string $key): void
    {
        $catalog = ComparisonCatalog::load();
        $package = $catalog['composer'][$key];
        $composer = $this->composerJson();

        self::assertArrayNotHasKey($package, $composer['require']);
        self::assertArrayHasKey($package, $composer['require-dev']);
    }

    /**
     * Представительный класс конкурента загружается из vendor.
     *
     * @param string $key Ключ каталога
     */
    #[DataProvider('competitorProvider')]
    public function testCompetitorClassIsAutoloadable(string $key): void
    {
        $catalog = ComparisonCatalog::load();
        $class = $catalog['classes'][$key];
        self::assertTrue(class_exists($class) || interface_exists($class), $class);
    }

    /**
     * Наш пакет не зависит runtime от конкурентов.
     */
    public function testRuntimeRequireHasNoCompetitors(): void
    {
        $composer = $this->composerJson();
        $require = $composer['require'];

        foreach (ComparisonCatalog::competitorKeys() as $key) {
            $package = ComparisonCatalog::load()['composer'][$key];
            self::assertArrayNotHasKey($package, $require);
        }

        self::assertArrayHasKey('php', $require);
        self::assertArrayHasKey('ext-gmp', $require);
        self::assertArrayHasKey('ext-openssl', $require);
        self::assertCount(3, $require);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function competitorProvider(): iterable
    {
        foreach (ComparisonCatalog::competitorKeys() as $key) {
            yield $key => [$key];
        }
    }

    /**
     * @return array{require: array<string, mixed>, require-dev: array<string, mixed>}
     */
    private function composerJson(): array
    {
        $raw = file_get_contents(dirname(__DIR__, 2) . '/composer.json');
        self::assertNotFalse($raw);
        $decoded = json_decode($raw, true);
        self::assertIsArray($decoded);
        $require = $decoded['require'] ?? null;
        $requireDev = $decoded['require-dev'] ?? null;
        self::assertIsArray($require);
        self::assertIsArray($requireDev);

        return [
            'require' => $this->stringKeyed($require),
            'require-dev' => $this->stringKeyed($requireDev),
        ];
    }

    /**
     * @param array<array-key, mixed> $input
     *
     * @return array<string, mixed>
     */
    private function stringKeyed(array $input): array
    {
        $out = [];

        foreach (array_keys($input) as $name) {
            if (!is_string($name)) {
                continue;
            }

            if (isset($input[$name]) && is_string($input[$name])) {
                $out[$name] = $input[$name];
            }
        }

        return $out;
    }
}
