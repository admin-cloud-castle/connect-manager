[ru](../../README.md) · **en** · [de](../de/README.md) · [fr](../fr/README.md) · [es](../es/README.md) · [it](../it/README.md)

<p align="center">
  <img src="../../assets/banner.svg" alt="Connect Manager" width="640">
</p>

# Connect Manager

> Translation stub — full content is in Russian ([README.md](../../README.md)).

Production-ready PHP 8.1+ Connect Manager library (CloudCastle).

```bash
composer require cloud-castle/connect-manager
```

[ru](../../README.md) · **en** · [de](../de/README.md) · [fr](../fr/README.md) · [es](../es/README.md) · [it](../it/README.md)


<!-- FEATURES:START -->
| Фича | Connect Manager | react/socket | amphp/socket | guzzle | phpseclib | socket-raw | 🏆 Победитель |
| --- | --- | --- | --- | --- | --- | --- | --- |
| PHP 8.1+ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Connect Manager |
| Единый Connection API для любого протокола | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | Connect Manager |
| Реестр протоколов OSI (100+) | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | Connect Manager |
| TCP / UDP / Unix / TLS нативные stream | ✅ | ✅ | ✅ | ❌ | ❌ | ✅ | Connect Manager |
| HTTP(S) как транспортный клиент | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ | Connect Manager |
| SSH / SFTP прикладной стек | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ | Connect Manager |
| Логические соединения для L1/L2 без native PHP | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | Connect Manager |
| Sync режим (обычное PHP-приложение) | ✅ | ❌ | ❌ | ✅ | ✅ | ✅ | Connect Manager |
| Async режим (Fibers / event-loop) | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | Connect Manager |
| Async без внешних event-loop зависимостей (Fibers) | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | Connect Manager |
| Пул соединений | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ | Connect Manager |
| SSRF-guard (блок частных адресов) | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | Connect Manager |
| TLS verify peer по умолчанию | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | Connect Manager |
| Генераторы чтения (экономия памяти) | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | Connect Manager |
| Пользовательская регистрация протоколов | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | Connect Manager |
| Ноль runtime-зависимостей Composer | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | Connect Manager |
| Raw sockets (ext-sockets) | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | Connect Manager |
| Только HTTP-ориентированный API | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | guzzlehttp/guzzle |
| Только ReactPHP event-loop | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | react/socket |
| Только Amp event-loop | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | amphp/socket |
<!-- FEATURES:END -->
