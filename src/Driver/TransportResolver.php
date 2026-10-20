<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Driver;

use CloudCastle\ConnectManager\Contract\ProtocolDefinitionInterface;
use CloudCastle\ConnectManager\Exception\ProtocolException;

/**
 * Строит URI транспорта PHP stream из протокола и endpoint.
 * @SuppressWarnings("PHPMD.CyclomaticComplexity")
 * @SuppressWarnings("PHPMD.NPathComplexity")
 * @SuppressWarnings("PHPMD.ShortVariable")
 */
final class TransportResolver
{
    /**
     * Карта протоколов на stream-схемы.
     *
     * @var array<string, string>
     */
    private const SCHEME_MAP = [
        'tcp' => 'tcp',
        'udp' => 'udp',
        'unix' => 'unix',
        'udg' => 'udg',
        'ssl' => 'ssl',
        'tls' => 'tls',
        'http' => 'tcp',
        'https' => 'tls',
        'ftp' => 'tcp',
        'ftps' => 'tls',
        'smtp' => 'tcp',
        'smtps' => 'tls',
        'imap' => 'tcp',
        'imaps' => 'tls',
        'pop3' => 'tcp',
        'pop3s' => 'tls',
        'ssh' => 'tcp',
        'telnet' => 'tcp',
        'dns' => 'udp',
        'ntp' => 'udp',
        'snmp' => 'udp',
        'tftp' => 'udp',
        'radius' => 'udp',
        'sip' => 'udp',
        'stun' => 'udp',
        'rtp' => 'udp',
        'ldap' => 'tcp',
        'ldaps' => 'tls',
        'socket' => 'tcp',
        'ws' => 'tcp',
        'wss' => 'tls',
        'mqtt' => 'tcp',
        'amqp' => 'tcp',
        'redis' => 'tcp',
        'mysql' => 'tcp',
        'pgsql' => 'tcp',
        'grpc' => 'tcp',
        'bgp' => 'tcp',
        'socks' => 'tcp',
        'irc' => 'tcp',
        'xmpp' => 'tcp',
        'nntp' => 'tcp',
        'rsync' => 'tcp',
        'rtsp' => 'tcp',
        'webdav' => 'tcp',
        'gopher' => 'tcp',
        'finger' => 'tcp',
        'dict' => 'tcp',
        'modbus' => 'tcp',
        'fix' => 'tcp',
        'sctp' => 'tcp',
        'ipv4' => 'tcp',
        'ipv6' => 'tcp',
        'ipsec' => 'tls',
        'icmp' => 'icmp',
        'l2tp' => 'udp',
        'l2f' => 'tcp',
        'pptp' => 'tcp',
        'smb' => 'tcp',
        'nfs' => 'tcp',
        '9p' => 'tcp',
        'rip' => 'udp',
        'dhcp' => 'udp',
        'bootp' => 'udp',
    ];

    /**
     * Порты по умолчанию для прикладных протоколов.
     *
     * @var array<string, int>
     */
    private const DEFAULT_PORTS = [
        'http' => 80,
        'https' => 443,
        'ftp' => 21,
        'ssh' => 22,
        'telnet' => 23,
        'smtp' => 25,
        'dns' => 53,
        'dhcp' => 67,
        'tftp' => 69,
        'gopher' => 70,
        'finger' => 79,
        'pop3' => 110,
        'nntp' => 119,
        'ntp' => 123,
        'imap' => 143,
        'snmp' => 161,
        'bgp' => 179,
        'ldap' => 389,
        'https-alt' => 443,
        'smtps' => 465,
        'syslog' => 514,
        'rtsp' => 554,
        'ldaps' => 636,
        'imaps' => 993,
        'pop3s' => 995,
        'socks' => 1080,
        'mysql' => 3306,
        'pgsql' => 5432,
        'redis' => 6379,
        'irc' => 6667,
        'ws' => 80,
        'wss' => 443,
        'mqtt' => 1883,
        'amqp' => 5672,
        'xmpp' => 5222,
        'sip' => 5060,
        'radius' => 1812,
        'modbus' => 502,
    ];

    /**
     * Строит stream URI.
     *
     * @throws ProtocolException
     */
    public function resolve(ProtocolDefinitionInterface $protocol, string $endpoint): string
    {
        $id = $protocol->id();

        if (str_contains($endpoint, '://')) {
            return $endpoint;
        }

        if ($id === 'unix' || $id === 'udg' || str_starts_with($endpoint, '/')) {
            $scheme = $id === 'udg' ? 'udg' : 'unix';

            return $scheme . '://' . $endpoint;
        }

        if (!$protocol->isNativeConnectable() && !isset(self::SCHEME_MAP[$id])) {
            throw new ProtocolException(sprintf(
                'Протокол %s не имеет нативного транспорта PHP; используйте LogicalConnection::attach()',
                $id,
            ));
        }

        $scheme = self::SCHEME_MAP[$id] ?? $protocol->transport();

        if (
            $scheme === 'none' || $scheme === 'raw' || $scheme === 'serial'
            || $scheme === 'custom' || $scheme === 'icmp'
        ) {
            throw new ProtocolException(sprintf(
                'Протокол %s требует пользовательский драйвер (transport=%s)',
                $id,
                $scheme,
            ));
        }

        if ($scheme === 'ip') {
            $scheme = 'tcp';
        }

        if ($scheme === 'socket') {
            $scheme = 'tcp';
        }

        $hostPort = $this->ensurePort($id, $endpoint);

        return $scheme . '://' . $hostPort;
    }

    /**
     * Добавляет порт по умолчанию при необходимости.
     */
    private function ensurePort(string $protocolId, string $endpoint): string
    {
        if (str_starts_with($endpoint, '[')) {
            if (str_contains($endpoint, ']:')) {
                return $endpoint;
            }

            $port = self::DEFAULT_PORTS[$protocolId] ?? 0;

            return $port > 0 ? $endpoint . ':' . $port : $endpoint;
        }

        $colon = strrpos($endpoint, ':');

        if ($colon !== false && ctype_digit(substr($endpoint, $colon + 1))) {
            return $endpoint;
        }

        $port = self::DEFAULT_PORTS[$protocolId] ?? 0;

        if ($port > 0) {
            return $endpoint . ':' . $port;
        }

        return $endpoint;
    }
}
