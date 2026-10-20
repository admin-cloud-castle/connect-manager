# Comparison

> Translation stub — see [Russian version](../ru/Comparison.md).

<!-- FEATURES:START -->
| Фича | Connect Manager | react/socket | amphp/socket | guzzle | phpseclib | socket-raw | 🏆 Победитель |
| --- | --- | --- | --- | --- | --- | --- | --- |
| PHP 8.1+ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Connect Manager |
| Единый Connection API для любого протокола | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | Connect Manager |
| Реестр протоколов OSI (100+) | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | Connect Manager |
| TCP / UDP / Unix / TLS нативные stream | ✅ | ✅ | ✅ | ❌ | ❌ | ✅ | Connect Manager |
| HTTP(S) прикладной клиент (JSON/form/redirects) | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ | Connect Manager |
| SSH / SFTP прикладной стек | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ | Connect Manager |
| SSRF-guard с DNS-resolve и anti-rebinding | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | Connect Manager |
| Non-blocking async connect (STREAM_CLIENT_ASYNC_CONNECT) | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | Connect Manager |
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
| Только HTTP-ориентированный API | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | guzzle |
| Только ReactPHP event-loop | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | react/socket |
| Только Amp event-loop | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | amphp/socket |
<!-- FEATURES:END -->

<!-- BENCH:START -->
| Метрика | Connect Manager | react/socket | amphp/socket | guzzle | phpseclib | socket-raw | 🏆 Победитель |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Конструкция клиента / connect (ns/op, avg PHP 8.5) | 5.6 µs | 38.4 µs | 198 ns | 17.6 µs | 64 ns | 76 ns | phpseclib |
| Registry lookup tcp (ns/op) | 204 ns | — | — | — | — | — | Connect Manager |
| Δ памяти на прогон конструкций | 0 B | 0 B | 0 B | 0 B | 0 B | 0 B | Connect Manager |
<!-- BENCH:END -->

<!-- THRESHOLDS:START -->
| Метрика | Порог | 🏆 Победитель |
| --- | --- | --- |
| Покрытие строк (per-file) | ≥ 99.8% | Connect Manager |
| Infection MSI | 100% | Connect Manager |
| PHPStan | level max | Connect Manager |
| Psalm | errorLevel 1 | Connect Manager |
| PHPCS | PSR-12 | Connect Manager |
<!-- THRESHOLDS:END -->

<!-- SECURITY:START -->
| Стандарт / практика | Connect Manager | react/socket | amphp/socket | guzzle | phpseclib | socket-raw | 🏆 Победитель |
| --- | --- | --- | --- | --- | --- | --- | --- |
| SECURITY.md / политика раскрытия уязвимостей | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Connect Manager |
| composer audit / FriendsOfPHP advisories в CI | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ | Connect Manager |
| SSRF-guard (блок частных/metadata IP по умолчанию) | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | Connect Manager |
| DNS pin / anti-rebinding после resolve | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | Connect Manager |
| TLS verify peer по умолчанию | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | Connect Manager |
| Явный opt-in для insecure / allow_private | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ | Connect Manager |
| Статанализ безопасности (PHPStan max / Psalm L1) в CI пакета | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | Connect Manager |
| Infection / мутационное тестирование с порогом MSI | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | Connect Manager |
| roave/security-advisories в require-dev | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | Connect Manager |
| Публичный API не тянет сторонний crypto/HTTP runtime | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | Connect Manager |
<!-- SECURITY:END -->
