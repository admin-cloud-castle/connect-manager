# Changelog

Все заметные изменения проекта документируются в этом файле.

Формат основан на [Keep a Changelog](https://keepachangelog.com/ru/1.1.0/),
проект следует [Semantic Versioning](https://semver.org/lang/ru/).

## [Unreleased]

## [1.0.0] - 2026-10-20

### Добавлено

- Реестр 150+ сетевых протоколов (модель OSI + socket/unix/tls/прикладные схемы)
- Единый `ConnectionInterface` для native stream и логических соединений
- Sync и async (Fibers + `stream_select`) без ReactPHP/Amp
- Пул соединений, SSRF-guard, TLS verify peer по умолчанию
- Генераторы чтения чанков для экономии памяти
- Сравнительные авто-таблицы с react/socket, amphp/socket, Guzzle, phpseclib, clue/socket-raw

## [0.0.1] - 2026-10-16

### Добавлено

- Скаффолд пакета cloud-castle/connect-manager
