<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Comparison;

use Amp\Socket\ConnectContext;
use Amp\Socket\DnsSocketConnector;
use CloudCastle\ConnectManager\ConnectManager;
use CloudCastle\ConnectManager\Contract\ConnectionOptions;
use GuzzleHttp\Client;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use React\Socket\Connector;
use ReflectionClass;
use Socket\Raw\Factory;

/**
 * Сравнение потребления памяти на типичных конструкциях каждого аналога.
 *
 * @SuppressWarnings("PHPMD.StaticAccess")
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
 */
#[Group('comparison')]
#[Group('memory')]
#[CoversNothing]
final class CompetitorMemoryComparisonTest extends TestCase
{
    private const ITERATIONS = 1500;

    /**
     * Дельта памяти после N созданий не должна быть аномальной ни у одного пакета.
     */
    public function testConstructionMemoryBudgetPerCompetitor(): void
    {
        $budgets = [
            'ours' => $this->measure(static function (): void {
                $manager = new ConnectManager();
                $options = ConnectionOptions::defaults()->allowPrivateAddresses();
                $conn = $manager->connect('adsl', 'line-mem', $options);
                $conn->close();
            }),
            'react/socket' => $this->measure(static function (): void {
                $connector = new Connector();
                unset($connector);
            }),
            'amphp/socket' => $this->measure(static function (): void {
                $connector = new DnsSocketConnector();
                $context = new ConnectContext();
                unset($connector, $context);
            }),
            'guzzlehttp/guzzle' => $this->measure(static function (): void {
                $client = new Client(['http_errors' => false]);
                unset($client);
            }),
            'phpseclib/phpseclib' => $this->measure(static function (): void {
                // Конструктор SSH2 трогает сеть при connect; измеряем только reflection класса.
                $ref = new ReflectionClass(\phpseclib3\Net\SSH2::class);
                unset($ref);
            }),
            'clue/socket-raw' => $this->measure(static function (): void {
                $factory = new Factory();
                unset($factory);
            }),
        ];

        foreach ($budgets as $name => $delta) {
            self::assertLessThan(
                32 * 1024 * 1024,
                $delta,
                sprintf('%s: подозрение на утечку/разрыв арены, delta=%d', $name, $delta),
            );
        }

        // Наш sync logical connect не должен жрать кратно больше лёгких фабрик.
        self::assertLessThan(
            max($budgets['react/socket'], $budgets['clue/socket-raw'], 1) * 40 + 8 * 1024 * 1024,
            $budgets['ours'],
            'ours memory delta слишком высок относительно лёгких аналогов',
        );
    }

    /**
     * @param callable():void $factory
     */
    private function measure(callable $factory): int
    {
        for ($i = 0; $i < 50; ++$i) {
            $factory();
        }

        gc_collect_cycles();
        $before = memory_get_usage(true);

        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            $factory();
        }

        gc_collect_cycles();

        return memory_get_usage(true) - $before;
    }
}
