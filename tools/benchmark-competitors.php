<?php

/**
 * Изолированные микро-бенчмарки vs аналоги → var/benchmark/comparison-<php>.json.
 * Запускать без Xdebug (иначе шум ~µs схлопывает различия).
 */

declare(strict_types=1);

if (extension_loaded('xdebug') && (string) ini_get('xdebug.mode') !== 'off' && (string) ini_get('xdebug.mode') !== '') {
    fwrite(STDERR, "предупреждение: xdebug активен — ns/op зашумлены; запускайте с -d xdebug.mode=off\n");
}

require_once __DIR__ . '/../vendor/autoload.php';

use Amp\Socket\ConnectContext;
use Amp\Socket\DnsSocketConnector;
use CloudCastle\ConnectManager\ConnectManager;
use CloudCastle\ConnectManager\Contract\ConnectionOptions;
use CloudCastle\ConnectManager\Protocol\ProtocolRegistry;
use GuzzleHttp\Client;
use React\Socket\Connector;
use Socket\Raw\Factory;

$iterations = 5000;
$warm = 50;

/**
 * @param callable():void $op
 *
 * @return array{ns_per_op: float, total_ns: int, memory_delta: int}
 */
$measure = static function (callable $op, int $iterations, int $warm): array {
    for ($i = 0; $i < $warm; ++$i) {
        $op();
    }

    gc_collect_cycles();
    $memBefore = memory_get_usage(true);
    $start = hrtime(true);

    for ($i = 0; $i < $iterations; ++$i) {
        $op();
    }

    $total = hrtime(true) - $start;
    gc_collect_cycles();
    $memAfter = memory_get_usage(true);

    return [
        'ns_per_op' => $total / max(1, $iterations),
        'total_ns' => $total,
        'memory_delta' => $memAfter - $memBefore,
    ];
};

$benches = [
    'ours_registry_lookup' => static function () use ($measure, $iterations, $warm): array {
        $registry = new ProtocolRegistry();

        return $measure(static function () use ($registry): void {
            $registry->get('tcp');
        }, $iterations, $warm);
    },
    'ours_logical_connect' => static function () use ($measure, $iterations, $warm): array {
        $manager = new ConnectManager();
        $options = ConnectionOptions::defaults()->allowPrivateAddresses();

        return $measure(static function () use ($manager, $options): void {
            $c = $manager->connect('adsl', 'bench', $options);
            $c->close();
        }, $iterations, $warm);
    },
    'react_connector_construct' => static fn (): array => $measure(static function (): void {
        $connector = new Connector();
        unset($connector);
    }, $iterations, $warm),
    'amphp_connector_construct' => static fn (): array => $measure(static function (): void {
        $connector = new DnsSocketConnector();
        $context = new ConnectContext();
        unset($connector, $context);
    }, $iterations, $warm),
    'guzzle_client_construct' => static fn (): array => $measure(static function (): void {
        $client = new Client(['http_errors' => false]);
        unset($client);
    }, $iterations, $warm),
    'socket_raw_factory_construct' => static fn (): array => $measure(static function (): void {
        $factory = new Factory();
        unset($factory);
    }, $iterations, $warm),
    'phpseclib_class_exists' => static fn (): array => $measure(static function (): void {
        class_exists(\phpseclib3\Net\SSH2::class);
        class_exists(\phpseclib3\Net\SFTP::class);
    }, $iterations, $warm),
];

$results = [
    'php' => PHP_VERSION,
    'php_id' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
    'iterations' => $iterations,
    'generated_at' => gmdate('c'),
    'benches' => [],
];

foreach ($benches as $name => $runner) {
    $results['benches'][$name] = $runner();
    fwrite(STDOUT, sprintf(
        "%s: %.0f ns/op, mem_delta=%d\n",
        $name,
        $results['benches'][$name]['ns_per_op'],
        $results['benches'][$name]['memory_delta'],
    ));
}

$dir = __DIR__ . '/../var/benchmark';

if (!is_dir($dir) && !mkdir($dir, 0o775, true) && !is_dir($dir)) {
    fwrite(STDERR, "cannot create {$dir}\n");
    exit(1);
}

$path = $dir . '/comparison-' . $results['php_id'] . '.json';
$encoded = json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

if ($encoded === false) {
    fwrite(STDERR, "json_encode failed\n");
    exit(1);
}

file_put_contents($path, $encoded . "\n");
fwrite(STDOUT, "wrote {$path}\n");
