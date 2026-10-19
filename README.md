**ru** · [en](.docs/en/README.md) · [de](.docs/de/README.md) · [fr](.docs/fr/README.md) · [es](.docs/es/README.md) · [it](.docs/it/README.md)

<p align="center">
  <img src="assets/banner.svg" alt="Connect Manager" width="720">
</p>

# Connect Manager

Production-ready PHP 8.1+ библиотека Connect Manager (CloudCastle).

<!-- Группа 1: Packagist (только живые shields endpoints) -->
[![Packagist Version](https://img.shields.io/packagist/v/cloud-castle/connect-manager?label=packagist)](https://packagist.org/packages/cloud-castle/connect-manager)
[![PHP Version](https://img.shields.io/packagist/php-v/cloud-castle/connect-manager)](https://packagist.org/packages/cloud-castle/connect-manager)
[![License](https://img.shields.io/packagist/l/cloud-castle/connect-manager)](LICENSE)
[![Downloads](https://img.shields.io/packagist/dt/cloud-castle/connect-manager)](https://packagist.org/packages/cloud-castle/connect-manager)
[![Monthly Downloads](https://img.shields.io/packagist/dm/cloud-castle/connect-manager)](https://packagist.org/packages/cloud-castle/connect-manager)
[![Packagist Stars](https://img.shields.io/packagist/stars/cloud-castle/connect-manager)](https://packagist.org/packages/cloud-castle/connect-manager)
[![Security Policy](https://img.shields.io/badge/security-policy-blue)](SECURITY.md)

<!-- Группа 2: GitHub Actions + meta -->
[![Quality](https://github.com/admin-cloud-castle/connect-manager/actions/workflows/quality.yml/badge.svg)](https://github.com/admin-cloud-castle/connect-manager/actions/workflows/quality.yml)
[![CodeQL](https://github.com/admin-cloud-castle/connect-manager/actions/workflows/codeql.yml/badge.svg)](https://github.com/admin-cloud-castle/connect-manager/actions/workflows/codeql.yml)
[![Semgrep](https://github.com/admin-cloud-castle/connect-manager/actions/workflows/semgrep.yml/badge.svg)](https://github.com/admin-cloud-castle/connect-manager/actions/workflows/semgrep.yml)
[![Supply chain](https://github.com/admin-cloud-castle/connect-manager/actions/workflows/supply-chain.yml/badge.svg)](https://github.com/admin-cloud-castle/connect-manager/actions/workflows/supply-chain.yml)
[![GitGuardian](https://github.com/admin-cloud-castle/connect-manager/actions/workflows/gitguardian.yml/badge.svg)](https://github.com/admin-cloud-castle/connect-manager/actions/workflows/gitguardian.yml)
[![Scorecard](https://github.com/admin-cloud-castle/connect-manager/actions/workflows/scorecard.yml/badge.svg)](https://github.com/admin-cloud-castle/connect-manager/actions/workflows/scorecard.yml)
[![GitHub stars](https://img.shields.io/github/stars/admin-cloud-castle/connect-manager?style=flat)](https://github.com/admin-cloud-castle/connect-manager/stargazers)
[![GitHub forks](https://img.shields.io/github/forks/admin-cloud-castle/connect-manager?style=flat)](https://github.com/admin-cloud-castle/connect-manager/network/members)
[![GitHub issues](https://img.shields.io/github/issues/admin-cloud-castle/connect-manager)](https://github.com/admin-cloud-castle/connect-manager/issues)
[![GitHub release](https://img.shields.io/github/v/release/admin-cloud-castle/connect-manager?include_prereleases)](https://github.com/admin-cloud-castle/connect-manager/releases)
[![Last commit](https://img.shields.io/github/last-commit/admin-cloud-castle/connect-manager)](https://github.com/admin-cloud-castle/connect-manager/commits/main)
[![Contributors](https://img.shields.io/github/contributors/admin-cloud-castle/connect-manager)](https://github.com/admin-cloud-castle/connect-manager/graphs/contributors)

<!-- Группа 3: качество — только живые метрики / ссылки без фейкового green -->
[![PHPStan](https://img.shields.io/badge/PHPStan-level%20max-blue)](https://github.com/admin-cloud-castle/connect-manager/actions/workflows/quality.yml)
[![PHPCS](https://img.shields.io/badge/PHPCS-PSR--12-blue)](https://github.com/admin-cloud-castle/connect-manager/actions/workflows/quality.yml)
[![Psalm](https://img.shields.io/badge/Psalm-level%201-blue)](https://github.com/admin-cloud-castle/connect-manager/actions/workflows/quality.yml)
[![Infection](https://img.shields.io/badge/Infection-CI-blue)](https://github.com/admin-cloud-castle/connect-manager/actions/workflows/quality.yml)
[![Coverage](https://coveralls.io/repos/github/admin-cloud-castle/connect-manager/badge.svg?branch=main)](https://coveralls.io/github/admin-cloud-castle/connect-manager?branch=main)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/admin-cloud-castle/connect-manager/badges/quality-score.png?b=main)](https://scrutinizer-ci.com/g/admin-cloud-castle/connect-manager/)
[![Scrutinizer Build](https://scrutinizer-ci.com/g/admin-cloud-castle/connect-manager/badges/build.png?b=main)](https://scrutinizer-ci.com/g/admin-cloud-castle/connect-manager/)
[![Maintainability](https://qlty.sh/gh/admin-cloud-castle/projects/connect-manager/maintainability.svg)](https://qlty.sh/gh/admin-cloud-castle/projects/connect-manager)
[![Qlty Coverage](https://qlty.sh/gh/admin-cloud-castle/projects/connect-manager/coverage.svg)](https://qlty.sh/gh/admin-cloud-castle/projects/connect-manager)
[![OpenSSF Scorecard](https://api.scorecard.dev/projects/github.com/admin-cloud-castle/connect-manager/badge)](https://scorecard.dev/viewer/?uri=github.com/admin-cloud-castle/connect-manager)
[![OpenSSF Best Practices](https://www.bestpractices.dev/projects/15549/badge)](https://www.bestpractices.dev/projects/15549)
[![SonarCloud](https://sonarcloud.io/api/project_badges/measure?project=admin-cloud-castle_connect-manager&metric=alert_status)](https://sonarcloud.io/summary/new_code?id=admin-cloud-castle_connect-manager)
[![Snyk](https://snyk.io/test/github/admin-cloud-castle/connect-manager/badge.svg)](https://snyk.io/test/github/admin-cloud-castle/connect-manager)
[![Codacy](https://img.shields.io/badge/Codacy-dashboard-blue)](https://app.codacy.com/gh/admin-cloud-castle/connect-manager/dashboard)
[![DeepSource](https://img.shields.io/badge/DeepSource-dashboard-blue)](https://app.deepsource.com/gh/admin-cloud-castle/connect-manager/)

## Зачем

Production-ready PHP 8.1+ библиотека Connect Manager (CloudCastle).

Краткая выжимка:

**Плюсы**
- PHP 8.1+, строгие типы, полный quality-стек в CI
- Цель эталона: покрытие ≥99.8%, Infection MSI 100% (по Coveralls / Quality CI)
- Минимум runtime-зависимостей

**Минусы** (допустимые по эталону CloudCastle)
- Пакет моложе и менее распространён, чем зрелые аналоги
- Меньше установок/звёзд на старте

## Когда применять

- Новый код на PHP 8.1+, где нужны предсказуемые контракты и зелёный CI
- Проекты экосистемы CloudCastle / `cloud-castle/*`

## Установка

```bash
composer require cloud-castle/connect-manager
```

## Быстрый старт

```php
<?php

declare(strict_types=1);

use CloudCastle\ConnectManager\ConnectManager;

$pkg = new ConnectManager();
echo $pkg->name();
```

## Документация

- [Wiki](https://github.com/admin-cloud-castle/connect-manager/wiki)
- [CHANGELOG](CHANGELOG.md)
- [SECURITY](SECURITY.md)
- [CONTRIBUTING](CONTRIBUTING.md)
- [DONATE](DONATE.md)
- [ROADMAP](ROADMAP.md)
- [AUTHORS](AUTHORS.md)
- [ACCESSIBILITY](ACCESSIBILITY.md)
- [RELEASE](RELEASE.md)
- [INSTALL](INSTALL.md)
- [ADOPTERS](ADOPTERS.md)
- [ARCHITECTURE](ARCHITECTURE.md)
- [FAQ](FAQ.md)
- [VERSIONING](VERSIONING.md)
- [CODE_STYLE](CODE_STYLE.md)

## Сравнение с аналогами

Актуальные таблицы (фичи / перф / память / безопасность / качество) — в [Wiki → Comparison](https://github.com/admin-cloud-castle/connect-manager/wiki/Comparison).
В README таблицы не дублируются (защита ветки main).

## Лицензия

MIT — см. [LICENSE](LICENSE).

**ru** · [en](.docs/en/README.md) · [de](.docs/de/README.md) · [fr](.docs/fr/README.md) · [es](.docs/es/README.md) · [it](.docs/it/README.md)
