<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Comparison;

use CloudCastle\ConnectManager\Protocol\ProtocolRegistry;
use GuzzleHttp\Client;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use React\Socket\Connector;
use Socket\Raw\Factory;

/**
 * Микро-бенчмарки производительности vs аналоги (регрессионные бюджеты).
 *
 * @SuppressWarnings("PHPMD.StaticAccess")
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
#[Group('comparison')]
#[Group('performance')]
#[CoversNothing]
final class CompetitorPerformanceComparisonTest extends TestCase
{
    /**
     * Lookup протокола быстрее создания Guzzle Client на сопоставимом числе итераций.
     */
    public function testRegistryFasterThanGuzzleConstruct(): void
    {
        $iterations = 5000;
        $registry = new ProtocolRegistry();

        $startRegistry = hrtime(true);
        for ($i = 0; $i < $iterations; ++$i) {
            $registry->get('tcp');
        }
        $registryNs = hrtime(true) - $startRegistry;

        $startGuzzle = hrtime(true);
        for ($i = 0; $i < $iterations; ++$i) {
            $client = new Client(['http_errors' => false]);
            unset($client);
        }
        $guzzleNs = hrtime(true) - $startGuzzle;

        self::assertLessThan($guzzleNs, $registryNs);
    }

    /**
     * Создание Factory (socket-raw) и ProtocolRegistry укладываются в общий бюджет.
     */
    public function testLightweightFactoriesBudget(): void
    {
        $iterations = 10000;
        $start = hrtime(true);

        $seen = false;

        for ($i = 0; $i < $iterations; ++$i) {
            $seen = (new ProtocolRegistry())->has('tcp') || $seen;
            $factory = new Factory();
            $connector = new Connector();
            unset($factory, $connector);
        }

        self::assertTrue($seen);

        $seconds = ((float) (hrtime(true) - $start)) / 1e9;
        self::assertLessThan(5.0, $seconds, sprintf('lightweight factories: %.3fs', $seconds));
    }
}
