<?php

declare(strict_types=1);

namespace CloudCastle\ConnectManager\Protocol;

/**
 * Каталог протоколов (список OSI + socket и распространённые прикладные).
 *
 * Источник базового списка: https://ru.wikipedia.org/wiki/Список_сетевых_протоколов
 *
 * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
 * @SuppressWarnings("PHPMD.ExcessiveClassLength")
 * @SuppressWarnings("PHPMD.StaticAccess")
 */
final class ProtocolCatalog
{
    /**
     * Возвращает генератор определений протоколов.
     *
     * @return \Generator<int, ProtocolDefinition>
     */
    public static function definitions(): \Generator
    {
        yield from self::layerOneAndTwo();
        yield from self::layerThreeAndFour();
        yield from self::layerFiveToSeven();
        yield from self::extraTransports();
    }

    /**
     * Число протоколов в каталоге.
     */
    public static function size(): int
    {
        return 160;
    }

    /**
     * Слои OSI 1–2.
     *
     * @return \Generator<int, ProtocolDefinition>
     */
    private static function layerOneAndTwo(): \Generator
    {
        yield new ProtocolDefinition('adsl', 'ADSL Asymmetric Digital Subscriber Line', 1, 'none', false, 'adsl');
        yield new ProtocolDefinition('isdn', 'ISDN Integrated Services Digital Network', 1, 'none', false, 'isdn');
        yield new ProtocolDefinition('pdh', 'PDH Плезиохронная цифровая иерархия', 1, 'none', false, 'pdh');
        yield new ProtocolDefinition('t1', 'T-канал T1', 1, 'none', false, 't1');
        yield new ProtocolDefinition('t3', 'T-канал T3', 1, 'none', false, 't3');
        yield new ProtocolDefinition('e1', 'E-канал E1', 1, 'none', false, 'e1');
        yield new ProtocolDefinition('e3', 'E-канал E3', 1, 'none', false, 'e3');
        yield new ProtocolDefinition('rs232', 'RS-232', 1, 'serial', false, 'rs232');
        yield new ProtocolDefinition('sdh', 'SDH Синхронная Цифровая Иерархия', 1, 'none', false, 'sdh');
        yield new ProtocolDefinition('sonet', 'SONET Synchronous Optical NETworking', 1, 'none', false, 'sonet');
        yield new ProtocolDefinition('itu-v', 'ITU V modem protocols', 1, 'none', false, 'itu-v');
        yield new ProtocolDefinition('ghn-phy', 'ITU-T G.hn physical', 1, 'none', false, 'ghn');
        yield new ProtocolDefinition('ethernet', 'Ethernet', 2, 'raw', false, 'eth');
        yield new ProtocolDefinition('gfp', 'GFP ITU-T G.7041', 2, 'none', false, 'gfp');
        yield new ProtocolDefinition('otn', 'OTN ITU-T G.709', 2, 'none', false, 'otn');
        yield new ProtocolDefinition('arcnet', 'ARCNET', 2, 'raw', false, 'arcnet');
        yield new ProtocolDefinition('atm', 'ATM Asynchronous Transport Mode', 2, 'raw', false, 'atm');
        yield new ProtocolDefinition('cdp', 'Cisco Discovery Protocol', 2, 'raw', false, 'cdp');
        yield new ProtocolDefinition('dcap', 'Data Link Switching Client Access Protocol', 2, 'tcp', true, 'dcap');
        yield new ProtocolDefinition('dtp', 'Dynamic Trunking Protocol', 2, 'raw', false, 'dtp');
        yield new ProtocolDefinition('econet', 'Econet', 2, 'raw', false, 'econet');
        yield new ProtocolDefinition('fddi', 'FDDI', 2, 'raw', false, 'fddi');
        yield new ProtocolDefinition('frame-relay', 'Frame Relay', 2, 'raw', false, 'fr');
        yield new ProtocolDefinition('ghn-dll', 'ITU-T G.hn data link', 2, 'none', false, 'ghn-dll');
        yield new ProtocolDefinition('hdlc', 'HDLC', 2, 'raw', false, 'hdlc');
        yield new ProtocolDefinition('wifi', 'IEEE 802.11 WiFi', 2, 'raw', false, 'wifi');
        yield new ProtocolDefinition('wimax', 'IEEE 802.16 WiMAX', 2, 'raw', false, 'wimax');
        yield new ProtocolDefinition('localtalk', 'LocalTalk', 2, 'raw', false, 'localtalk');
        yield new ProtocolDefinition('l2f', 'Layer 2 Forwarding Protocol', 2, 'tcp', true, 'l2f');
        yield new ProtocolDefinition('l2tp', 'Layer 2 Tunneling Protocol', 2, 'udp', true, 'l2tp');
        yield new ProtocolDefinition('lapd', 'LAPD', 2, 'raw', false, 'lapd');
        yield new ProtocolDefinition('lldp', 'Link Layer Discovery Protocol', 2, 'raw', false, 'lldp');
        yield new ProtocolDefinition('lldp-med', 'LLDP-MED', 2, 'raw', false, 'lldp-med');
        yield new ProtocolDefinition('ppp', 'Point-to-Point Protocol', 2, 'raw', false, 'ppp');
        yield new ProtocolDefinition('pptp', 'Point-to-Point Tunneling Protocol', 2, 'tcp', true, 'pptp');
        yield new ProtocolDefinition('q710', 'Q.710', 2, 'raw', false, 'q710');
        yield new ProtocolDefinition('ndp', 'Neighbor Discovery Protocol', 3, 'raw', false, 'ndp');
        yield new ProtocolDefinition('rpr', 'IEEE 802.17 Resilient Packet Ring', 2, 'raw', false, 'rpr');
        yield new ProtocolDefinition('spb', 'IEEE 802.1aq Shortest Path Bridging', 2, 'raw', false, 'spb');
        yield new ProtocolDefinition('slip', 'Serial Line Internet Protocol', 2, 'serial', false, 'slip');
        yield new ProtocolDefinition('starlan', 'StarLAN', 2, 'raw', false, 'starlan');
        yield new ProtocolDefinition('stp', 'Spanning Tree Protocol', 2, 'raw', false, 'stp');
        yield new ProtocolDefinition('token-ring', 'Token ring', 2, 'raw', false, 'tokenring');
        yield new ProtocolDefinition('vtp', 'VLAN Trunking Protocol', 2, 'raw', false, 'vtp');
    }

    /**
     * Слои OSI 3–4 и смежные.
     *
     * @return \Generator<int, ProtocolDefinition>
     */
    private static function layerThreeAndFour(): \Generator
    {
        yield new ProtocolDefinition('mpls', 'MPLS', 3, 'raw', false, 'mpls');
        yield new ProtocolDefinition('x25', 'X.25', 3, 'raw', false, 'x25');
        yield new ProtocolDefinition('arp', 'Address Resolution Protocol', 3, 'raw', false, 'arp');
        yield new ProtocolDefinition('rarp', 'Reverse ARP', 3, 'raw', false, 'rarp');
        yield new ProtocolDefinition('mtp', 'Message Transfer Part', 3, 'raw', false, 'mtp');
        yield new ProtocolDefinition('nsp', 'Network Service Part', 3, 'raw', false, 'nsp');
        yield new ProtocolDefinition('clnp', 'CLNP', 3, 'raw', false, 'clnp');
        yield new ProtocolDefinition('egp', 'Exterior Gateway Protocol', 3, 'raw', false, 'egp');
        yield new ProtocolDefinition('eigrp', 'EIGRP', 3, 'raw', false, 'eigrp');
        yield new ProtocolDefinition('icmp', 'Internet Control Message Protocol', 3, 'raw', true, 'icmp');
        yield new ProtocolDefinition('igmp', 'Internet Group Management Protocol', 3, 'raw', false, 'igmp');
        yield new ProtocolDefinition('igrp', 'IGRP', 3, 'raw', false, 'igrp');
        yield new ProtocolDefinition('ipv4', 'Internet Protocol version 4', 3, 'ip', true, 'ip');
        yield new ProtocolDefinition('ipv6', 'Internet Protocol version 6', 3, 'ip', true, 'ip6');
        yield new ProtocolDefinition('ipsec', 'Internet Protocol Security', 3, 'ip', true, 'ipsec');
        yield new ProtocolDefinition('ipx', 'Internetwork Packet Exchange', 3, 'raw', false, 'ipx');
        yield new ProtocolDefinition('sccp', 'Signalling Connection Control Part', 3, 'raw', false, 'sccp');
        yield new ProtocolDefinition('ddp', 'AppleTalk DDP', 3, 'raw', false, 'ddp');
        yield new ProtocolDefinition('isis', 'IS-IS', 3, 'raw', false, 'isis');
        yield new ProtocolDefinition('ospf', 'OSPF', 3, 'raw', false, 'ospf');
        yield new ProtocolDefinition('bgp', 'Border Gateway Protocol', 4, 'tcp', true, 'bgp');
        yield new ProtocolDefinition('rip', 'Routing Information Protocol', 4, 'udp', true, 'rip');
        yield new ProtocolDefinition('irdp', 'ICMP Router Discovery Protocol', 3, 'raw', false, 'irdp');
        yield new ProtocolDefinition('gdp', 'Gateway Discovery Protocol', 3, 'raw', false, 'gdp');
        yield new ProtocolDefinition('hip', 'Host Identity Protocol', 3, 'ip', false, 'hip');
        yield new ProtocolDefinition('xns', 'Xerox Network Systems', 4, 'raw', false, 'xns');
        yield new ProtocolDefinition('ah', 'IP Authentication Header', 4, 'ip', false, 'ah');
        yield new ProtocolDefinition('esp', 'Encapsulating Security Payload', 4, 'ip', false, 'esp');
        yield new ProtocolDefinition('gre', 'Generic Routing Encapsulation', 4, 'ip', false, 'gre');
        yield new ProtocolDefinition('il', 'IL protocol', 4, 'raw', false, 'il');
        yield new ProtocolDefinition('sctp', 'Stream Control Transmission Protocol', 4, 'sctp', true, 'sctp');
        yield new ProtocolDefinition('sinec-h1', 'Sinec H1', 4, 'raw', false, 'sinec');
        yield new ProtocolDefinition('spx', 'Sequenced Packet Exchange', 4, 'raw', false, 'spx');
        yield new ProtocolDefinition('tcp', 'Transmission Control Protocol', 4, 'tcp', true, 'tcp');
        yield new ProtocolDefinition('udp', 'User Datagram Protocol', 4, 'udp', true, 'udp');
        yield new ProtocolDefinition('9p', '9P Plan 9', 5, 'tcp', true, '9p');
        yield new ProtocolDefinition('ncp', 'NetWare Core Protocol', 5, 'raw', false, 'ncp');
        yield new ProtocolDefinition('nfs', 'Network File System', 5, 'tcp', true, 'nfs');
        yield new ProtocolDefinition('smb', 'Server Message Block', 5, 'tcp', true, 'smb');
        yield new ProtocolDefinition('socks', 'SOCKS', 5, 'tcp', true, 'socks');
    }

    /**
     * Прикладные протоколы и индустриальные.
     *
     * @return \Generator<int, ProtocolDefinition>
     */
    private static function layerFiveToSeven(): \Generator
    {
        yield new ProtocolDefinition('can', 'Controller Area Network', 0, 'raw', false, 'can');
        yield new ProtocolDefinition('cip', 'Common Industrial Protocol', 0, 'tcp', true, 'cip');
        yield new ProtocolDefinition('dcc', 'Digital Command Control', 0, 'raw', false, 'dcc');
        yield new ProtocolDefinition('fix', 'Financial Information eXchange', 7, 'tcp', true, 'fix');
        yield new ProtocolDefinition('i2c', 'I²C', 0, 'raw', false, 'i2c');
        yield new ProtocolDefinition('modbus', 'Modbus', 7, 'tcp', true, 'modbus');
        yield new ProtocolDefinition('decnet', 'DECnet', 0, 'raw', false, 'decnet');
        yield new ProtocolDefinition('slp', 'Service Location Protocol', 7, 'udp', true, 'slp');
        yield new ProtocolDefinition('sap', 'Service Advertising Protocol', 7, 'raw', false, 'sap');
        yield new ProtocolDefinition('adc', 'ADC P2P', 7, 'tcp', true, 'adc');
        yield new ProtocolDefinition('afp', 'Apple Filing Protocol', 7, 'tcp', true, 'afp');
        yield new ProtocolDefinition('bacnet', 'BACnet', 7, 'udp', true, 'bacnet');
        yield new ProtocolDefinition('bittorrent', 'BitTorrent', 7, 'tcp', true, 'bittorrent');
        yield new ProtocolDefinition('bootp', 'Bootstrap Protocol', 7, 'udp', true, 'bootp');
        yield new ProtocolDefinition('diameter', 'DIAMETER', 7, 'tcp', true, 'diameter');
        yield new ProtocolDefinition('dicom', 'DICOM', 7, 'tcp', true, 'dicom');
        yield new ProtocolDefinition('dict', 'DICT', 7, 'tcp', true, 'dict');
        yield new ProtocolDefinition('dns', 'DNS', 7, 'udp', true, 'dns');
        yield new ProtocolDefinition('dhcp', 'DHCP', 7, 'udp', true, 'dhcp');
        yield new ProtocolDefinition('ed2k', 'eDonkey2000', 7, 'tcp', true, 'ed2k');
        yield new ProtocolDefinition('ftp', 'FTP', 7, 'tcp', true, 'ftp');
        yield new ProtocolDefinition('finger', 'Finger', 7, 'tcp', true, 'finger');
        yield new ProtocolDefinition('gnutella', 'Gnutella', 7, 'tcp', true, 'gnutella');
        yield new ProtocolDefinition('gopher', 'Gopher', 7, 'tcp', true, 'gopher');
        yield new ProtocolDefinition('http', 'HTTP (прикладной клиент)', 7, 'tcp', true, 'http');
        yield new ProtocolDefinition('https', 'HTTPS (прикладной клиент)', 7, 'tcp', true, 'https');
        yield new ProtocolDefinition('imap', 'IMAP', 7, 'tcp', true, 'imap');
        yield new ProtocolDefinition('imaps', 'IMAPS', 7, 'tcp', true, 'imaps');
        yield new ProtocolDefinition('irc', 'IRC', 7, 'tcp', true, 'irc');
        yield new ProtocolDefinition('isup', 'ISUP', 7, 'raw', false, 'isup');
        yield new ProtocolDefinition('xmpp', 'XMPP', 7, 'tcp', true, 'xmpp');
        yield new ProtocolDefinition('ldap', 'LDAP', 7, 'tcp', true, 'ldap');
        yield new ProtocolDefinition('ldaps', 'LDAPS', 7, 'tcp', true, 'ldaps');
        yield new ProtocolDefinition('mime', 'MIME', 7, 'none', false, 'mime');
        yield new ProtocolDefinition('msnp', 'MSNP', 7, 'tcp', true, 'msnp');
        yield new ProtocolDefinition('map', 'Mobile Application Part', 7, 'raw', false, 'map');
        yield new ProtocolDefinition('netbios', 'NetBIOS', 7, 'tcp', true, 'netbios');
        yield new ProtocolDefinition('nntp', 'NNTP', 7, 'tcp', true, 'nntp');
        yield new ProtocolDefinition('ntp', 'NTP', 7, 'udp', true, 'ntp');
        yield new ProtocolDefinition('ntcip', 'NTCIP', 7, 'tcp', true, 'ntcip');
        yield new ProtocolDefinition('pop3', 'POP3', 7, 'tcp', true, 'pop3');
        yield new ProtocolDefinition('pop3s', 'POP3S', 7, 'tcp', true, 'pop3s');
        yield new ProtocolDefinition('radius', 'RADIUS', 7, 'udp', true, 'radius');
        yield new ProtocolDefinition('rlogin', 'rlogin', 7, 'tcp', true, 'rlogin');
        yield new ProtocolDefinition('rsync', 'rsync', 7, 'tcp', true, 'rsync');
        yield new ProtocolDefinition('rtp', 'RTP', 7, 'udp', true, 'rtp');
        yield new ProtocolDefinition('rtsp', 'RTSP', 7, 'tcp', true, 'rtsp');
        yield new ProtocolDefinition('ssh', 'SSH (прикладной клиент)', 7, 'tcp', true, 'ssh');
        yield new ProtocolDefinition('sftp', 'SFTP (прикладной клиент)', 7, 'tcp', true, 'sftp');
        yield new ProtocolDefinition('sisnapi', 'SISNAPI', 7, 'tcp', true, 'sisnapi');
        yield new ProtocolDefinition('sip', 'SIP', 7, 'udp', true, 'sip');
        yield new ProtocolDefinition('smtp', 'SMTP', 7, 'tcp', true, 'smtp');
        yield new ProtocolDefinition('smtps', 'SMTPS', 7, 'tcp', true, 'smtps');
        yield new ProtocolDefinition('snmp', 'SNMP', 7, 'udp', true, 'snmp');
        yield new ProtocolDefinition('soap', 'SOAP', 7, 'tcp', true, 'soap');
        yield new ProtocolDefinition('stun', 'STUN', 7, 'udp', true, 'stun');
        yield new ProtocolDefinition('tup', 'Telephone User Part', 7, 'raw', false, 'tup');
        yield new ProtocolDefinition('telnet', 'Telnet', 7, 'tcp', true, 'telnet');
        yield new ProtocolDefinition('tcap', 'TCAP', 7, 'raw', false, 'tcap');
        yield new ProtocolDefinition('tftp', 'TFTP', 7, 'udp', true, 'tftp');
        yield new ProtocolDefinition('webdav', 'WebDAV', 7, 'tcp', true, 'webdav');
        yield new ProtocolDefinition('dsm-cc', 'DSM CC', 7, 'tcp', true, 'dsmcc');
    }

    /**
     * Socket/TLS/WS и популярные прикладные транспорты.
     *
     * @return \Generator<int, ProtocolDefinition>
     */
    private static function extraTransports(): \Generator
    {
        yield new ProtocolDefinition('socket', 'Generic socket', 0, 'socket', true, 'socket');
        yield new ProtocolDefinition('unix', 'Unix domain socket', 0, 'unix', true, 'unix');
        yield new ProtocolDefinition('udg', 'Unix datagram socket', 0, 'unix', true, 'udg');
        yield new ProtocolDefinition('ssl', 'SSL stream', 5, 'tcp', true, 'ssl');
        yield new ProtocolDefinition('tls', 'TLS stream', 5, 'tcp', true, 'tls');
        yield new ProtocolDefinition('ws', 'WebSocket', 7, 'tcp', true, 'ws');
        yield new ProtocolDefinition('wss', 'WebSocket Secure', 7, 'tcp', true, 'wss');
        yield new ProtocolDefinition('quic', 'QUIC', 4, 'udp', false, 'quic');
        yield new ProtocolDefinition('mqtt', 'MQTT', 7, 'tcp', true, 'mqtt');
        yield new ProtocolDefinition('amqp', 'AMQP', 7, 'tcp', true, 'amqp');
        yield new ProtocolDefinition('redis', 'Redis protocol', 7, 'tcp', true, 'redis');
        yield new ProtocolDefinition('mysql', 'MySQL protocol', 7, 'tcp', true, 'mysql');
        yield new ProtocolDefinition('pgsql', 'PostgreSQL protocol', 7, 'tcp', true, 'pgsql');
        yield new ProtocolDefinition('grpc', 'gRPC', 7, 'tcp', true, 'grpc');
    }
}
