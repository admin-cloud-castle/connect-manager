<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Tests\Comparison;

use Amp\Socket\ConnectContext;
use Amp\Socket\DnsSocketConnector;
use CloudCastle\ConnectManager\ConnectManager;
use CloudCastle\ConnectManager\Contract\ConnectionOptions;
use CloudCastle\ConnectManager\Protocol\ProtocolRegistry;
use GuzzleHttp\Client;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use React\Socket\Connector;
use Socket\Raw\Factory;

/**
 * Нагрузочные сравнительные прогоны конструкций (без внешней сети).
 *
 * @SuppressWarnings("PHPMD.StaticAccess")
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
#[Group('comparison')]
#[Group('load')]
#[CoversNothing]
final class CompetitorLoadComparisonTest extends TestCase
{
    private const ITERATIONS = 8000;

    /**
     * Каждый аналог выдерживает N созданий в бюджете времени.
     */
    public function testConstructionThroughputBudget(): void
    {
        $results = [
            'ours_registry' => $this->timed(static function (): void {
                (new ProtocolRegistry())->get('tcp');
            }),
            'ours_logical' => $this->timed(static function (): void {
                $manager = new ConnectManager();
                $conn = $manager->connect('adsl', 'load-line', ConnectionOptions::defaults()->allowPrivateAddresses());
                $conn->close();
            }),
            'react/socket' => $this->timed(static function (): void {
                $connector = new Connector();
                unset($connector);
            }),
            'amphp/socket' => $this->timed(static function (): void {
                $connector = new DnsSocketConnector();
                $context = new ConnectContext();
                unset($connector, $context);
            }),
            'guzzlehttp/guzzle' => $this->timed(static function (): void {
                $client = new Client(['http_errors' => false]);
                unset($client);
            }),
            'clue/socket-raw' => $this->timed(static function (): void {
                $factory = new Factory();
                unset($factory);
            }),
            'phpseclib/phpseclib' => $this->timed(static function (): void {
                class_exists(\phpseclib3\Net\SSH2::class);
                class_exists(\phpseclib3\Net\SFTP::class);
            }),
        ];

        foreach ($results as $name => $seconds) {
            self::assertLessThan(
                15.0,
                $seconds,
                sprintf('%s: нагрузка %.3fs превышает бюджет 15s', $name, $seconds),
            );
        }

        self::assertArrayHasKey('ours_registry', $results);
        self::assertArrayHasKey('guzzlehttp/guzzle', $results);
    }

    /**
     * @param callable():void $operation
     */
    private function timed(callable $operation): float
    {
        for ($i = 0; $i < 20; ++$i) {
            $operation();
        }

        $start = hrtime(true);

        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            $operation();
        }

        return ((float) (hrtime(true) - $start)) / 1e9;
    }
}
