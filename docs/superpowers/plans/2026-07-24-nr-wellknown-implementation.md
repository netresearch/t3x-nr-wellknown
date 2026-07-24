# nr_wellknown Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** a generic TYPO3 extension `netresearch/nr-wellknown` that serves the in-scope well-known resources from per-site configuration — static files for content, one PSR-15 middleware for the change-password redirect — plus the one-line t3re nginx change and the netresearch.de application.

**Architecture:** A console command `nr:wellknown:generate` renders the configured resources into the docroot `public/.well-known/` (and `public/llms.txt`); nginx serves those static files directly. A PSR-15 middleware handles only `GET /.well-known/change-password` → 302, reached because the t3re `.well-known` location gains a `try_files … @t3frontend` fallthrough. Values come from each site's `config.yaml` under a `wellknown` key.

**Tech Stack:** TYPO3 v13.4/v14, PHP ≥8.2, Symfony Console, PSR-15, PHPUnit via `netresearch/typo3-ci-workflows`.

## Global Constraints

- PHP `>=8.2`; TYPO3 `^13.4 || ^14.0`. Every PHP file starts with the NR header block and `declare(strict_types=1);`.
- Namespace `Netresearch\NrWellknown\` → `Classes/`. Extension key `nr_wellknown`. Composer name `netresearch/nr-wellknown`. License `GPL-3.0-or-later`.
- Composer config mirrors the NR house skeleton: `bin-dir: .build/bin`, `vendor-dir: .build/vendor`, `sort-packages: true`, dev-dep `netresearch/typo3-ci-workflows: ^1.1`.
- Services via `Configuration/Services.yaml` with `autowire: true`, `autoconfigure: true`, `public: false`; commands register through the `#[AsCommand]` attribute (autoconfigure adds the tag — no manual tag).
- Never fabricate an excluded resource (§2 of the spec): only the 5 in-scope files, each emitted only when its required config is present.
- Gates before every commit: `composer ci:test` (or the project's `Makefile` targets) — at minimum PHPUnit, PHPStan, and the CGL check the CI-workflows meta-package runs.
- Commits: Conventional Commits, signed + DCO (`git commit -S -s`), atomic, no AI/bot attribution. No version bump / CHANGELOG in feature commits.
- Spec: `docs/superpowers/specs/2026-07-24-static-wellknown-typo3-design.md` is the authority.

## File Structure

| File | Responsibility |
|---|---|
| `composer.json`, `ext_emconf.php` | Extension manifest, NR skeleton |
| `Configuration/Services.yaml` | DI: autowire, command autoconfigure |
| `Configuration/RequestMiddlewares.php` | Register the change-password middleware (after site resolution) |
| `Classes/Configuration/WellKnownConfig.php` | Immutable DTO of one site's `wellknown` config + `fromSite()` factory with defaults |
| `Classes/Resource/SecurityTxt.php` | Render RFC 9116 security.txt with rolling `Expires` |
| `Classes/Resource/StaticResources.php` | Render gpc.json, llms.txt, agent-skills.json |
| `Classes/Command/GenerateCommand.php` | `nr:wellknown:generate` — write files into the docroot |
| `Classes/Middleware/ChangePasswordMiddleware.php` | 302 for `/.well-known/change-password` |
| `Tests/Unit/…`, `Tests/Functional/…` | Test suite |
| `README.rst`, `AGENTS.md` | Docs (verified, house style) |

**Cross-repo (separate MRs, Tasks 9–10):** `netresearch/t3re` nginx line; `netresearch/netresearch` site config + deploy wiring.

---

## Task 1: Extension skeleton

**Files:**
- Create: `composer.json`, `ext_emconf.php`, `LICENSE`, `Configuration/Services.yaml`, `.gitignore`

**Interfaces:**
- Produces: composer package `netresearch/nr-wellknown`, autoload `Netresearch\NrWellknown\` → `Classes/`.

- [ ] **Step 1: Write `composer.json`**

```json
{
    "name": "netresearch/nr-wellknown",
    "description": "Serve the well-known resources a TYPO3 site should provide (security.txt, change-password, gpc.json, llms.txt, agent-skills.json) from per-site configuration.",
    "type": "typo3-cms-extension",
    "license": "GPL-3.0-or-later",
    "keywords": ["typo3", "well-known", "security.txt", "rfc9116", "gpc"],
    "authors": [{ "name": "Team der Netresearch DTT GmbH", "homepage": "https://www.netresearch.de" }],
    "config": {
        "bin-dir": ".build/bin",
        "vendor-dir": ".build/vendor",
        "sort-packages": true,
        "allow-plugins": { "typo3/cms-composer-installers": true, "typo3/class-alias-loader": true }
    },
    "require": {
        "php": ">=8.2",
        "typo3/cms-core": "^13.4 || ^14.0"
    },
    "require-dev": {
        "netresearch/typo3-ci-workflows": "^1.1"
    },
    "autoload": { "psr-4": { "Netresearch\\NrWellknown\\": "Classes/" } },
    "autoload-dev": { "psr-4": { "Netresearch\\NrWellknown\\Tests\\": "Tests/" } },
    "extra": { "typo3/cms": { "extension-key": "nr_wellknown" } }
}
```

- [ ] **Step 2: Write `ext_emconf.php`**

```php
<?php

/*
 * This file is part of the package netresearch/nr-wellknown.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

$EM_CONF['nr_wellknown'] = [
    'title'          => 'Netresearch: Well-Known Resources',
    'description'    => 'Serve the well-known resources a TYPO3 site should provide, from per-site configuration.',
    'category'       => 'fe',
    'author'         => 'Team der Netresearch DTT GmbH',
    'author_company' => 'Netresearch DTT GmbH',
    'state'          => 'beta',
    'version'        => '0.1.0',
    'constraints'    => [
        'depends'   => ['php' => '8.2.0-8.5.99', 'typo3' => '13.4.0-14.4.99'],
        'conflicts' => [],
        'suggests'  => [],
    ],
];
```

- [ ] **Step 3: Write `Configuration/Services.yaml`**

```yaml
services:
  _defaults:
    autowire: true
    autoconfigure: true
    public: false

  Netresearch\NrWellknown\:
    resource: '../Classes/*'
```

- [ ] **Step 4: Write `LICENSE`** — the GPL-3.0-or-later text (copy from `~/p/nr-image-optimize/main/LICENSE`) and a `.gitignore` with `.build/` and `composer.lock`.

- [ ] **Step 5: Verify**

Run: `composer validate --no-check-publish && composer install`
Expected: `./composer.json is valid` and a clean install into `.build/`.

- [ ] **Step 6: Commit**

```bash
git add composer.json ext_emconf.php Configuration/Services.yaml LICENSE .gitignore
git commit -S -s -m "feat: scaffold the nr_wellknown extension"
```

---

## Task 2: `WellKnownConfig` DTO

**Files:**
- Create: `Classes/Configuration/WellKnownConfig.php`, `Tests/Unit/Configuration/WellKnownConfigTest.php`

**Interfaces:**
- Produces: `WellKnownConfig::fromSite(Site $site): self` and readonly accessors: `securityContacts(): array`, `securityPolicy(): ?string`, `preferredLanguages(): array`, `expiresMonths(): int`, `changePasswordTarget(): ?string`, `gpcEnabled(): bool`, `llmsSource(): ?string`, `agentSkills(): array`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Netresearch\NrWellknown\Tests\Unit\Configuration;

use Netresearch\NrWellknown\Configuration\WellKnownConfig;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Site\Entity\Site;

final class WellKnownConfigTest extends TestCase
{
    public function testReadsValuesFromSiteWithDefaults(): void
    {
        $site = new Site('main', 1, [
            'base'      => 'https://www.netresearch.de/',
            'wellknown' => [
                'security'       => ['contacts' => ['mailto:security@netresearch.de']],
                'changePassword' => ['target' => 'https://www.netresearch.de/passwort'],
            ],
        ]);
        $config = WellKnownConfig::fromSite($site);

        self::assertSame(['mailto:security@netresearch.de'], $config->securityContacts());
        self::assertSame(6, $config->expiresMonths());          // default
        self::assertTrue($config->gpcEnabled());                // default true
        self::assertSame('https://www.netresearch.de/passwort', $config->changePasswordTarget());
    }

    public function testAbsentSectionsYieldSafeDefaults(): void
    {
        $config = WellKnownConfig::fromSite(new Site('empty', 2, ['base' => 'https://x.test/']));
        self::assertSame([], $config->securityContacts());
        self::assertNull($config->changePasswordTarget());
        self::assertTrue($config->gpcEnabled());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `.build/bin/phpunit Tests/Unit/Configuration/WellKnownConfigTest.php`
Expected: FAIL — `Class "Netresearch\NrWellknown\Configuration\WellKnownConfig" not found`.

- [ ] **Step 3: Write the implementation**

```php
<?php

/*
 * This file is part of the package netresearch/nr-wellknown.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrWellknown\Configuration;

use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Immutable view of one site's `wellknown` configuration, with safe defaults.
 */
final readonly class WellKnownConfig
{
    /**
     * @param list<string> $securityContacts
     * @param list<string> $preferredLanguages
     * @param array<mixed> $agentSkills
     */
    public function __construct(
        private array $securityContacts,
        private ?string $securityPolicy,
        private array $preferredLanguages,
        private int $expiresMonths,
        private ?string $changePasswordTarget,
        private bool $gpcEnabled,
        private ?string $llmsSource,
        private array $agentSkills,
    ) {}

    public static function fromSite(Site $site): self
    {
        $wk = $site->getConfiguration()['wellknown'] ?? [];

        return new self(
            securityContacts: array_values(array_filter((array) ($wk['security']['contacts'] ?? []), 'is_string')),
            securityPolicy: isset($wk['security']['policy']) ? (string) $wk['security']['policy'] : null,
            preferredLanguages: array_values(array_filter((array) ($wk['security']['preferredLanguages'] ?? []), 'is_string')),
            expiresMonths: (int) ($wk['security']['expiresMonths'] ?? 6),
            changePasswordTarget: isset($wk['changePassword']['target']) ? (string) $wk['changePassword']['target'] : null,
            gpcEnabled: (bool) ($wk['gpc'] ?? true),
            llmsSource: isset($wk['llms']['source']) ? (string) $wk['llms']['source'] : null,
            agentSkills: (array) ($wk['agentSkills']['skills'] ?? []),
        );
    }

    /** @return list<string> */
    public function securityContacts(): array { return $this->securityContacts; }
    public function securityPolicy(): ?string { return $this->securityPolicy; }
    /** @return list<string> */
    public function preferredLanguages(): array { return $this->preferredLanguages; }
    public function expiresMonths(): int { return max(1, $this->expiresMonths); }
    public function changePasswordTarget(): ?string { return $this->changePasswordTarget; }
    public function gpcEnabled(): bool { return $this->gpcEnabled; }
    public function llmsSource(): ?string { return $this->llmsSource; }
    /** @return array<mixed> */
    public function agentSkills(): array { return $this->agentSkills; }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `.build/bin/phpunit Tests/Unit/Configuration/WellKnownConfigTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add Classes/Configuration/WellKnownConfig.php Tests/Unit/Configuration/WellKnownConfigTest.php
git commit -S -s -m "feat(config): read per-site wellknown configuration with defaults"
```

---

## Task 3: `SecurityTxt` renderer (rolling Expires)

**Files:**
- Create: `Classes/Resource/SecurityTxt.php`, `Tests/Unit/Resource/SecurityTxtTest.php`

**Interfaces:**
- Consumes: `WellKnownConfig`.
- Produces: `SecurityTxt::render(WellKnownConfig $c, \DateTimeImmutable $now): ?string` — returns the RFC 9116 body, or `null` when no contact is configured (so the caller writes nothing).

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Netresearch\NrWellknown\Tests\Unit\Resource;

use Netresearch\NrWellknown\Configuration\WellKnownConfig;
use Netresearch\NrWellknown\Resource\SecurityTxt;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Site\Entity\Site;

final class SecurityTxtTest extends TestCase
{
    private function config(array $wk): WellKnownConfig
    {
        return WellKnownConfig::fromSite(new Site('s', 1, ['base' => 'https://x.test/', 'wellknown' => $wk]));
    }

    public function testRendersRfc9116WithFutureExpires(): void
    {
        $now  = new \DateTimeImmutable('2026-07-24T09:00:00Z');
        $body = SecurityTxt::render($this->config([
            'security' => ['contacts' => ['mailto:security@x.test'], 'policy' => 'https://x.test/p', 'preferredLanguages' => ['de', 'en']],
        ]), $now);

        self::assertNotNull($body);
        self::assertStringContainsString('Contact: mailto:security@x.test', $body);
        self::assertStringContainsString('Policy: https://x.test/p', $body);
        self::assertStringContainsString('Preferred-Languages: de, en', $body);
        self::assertStringContainsString('Expires: 2027-01-24T00:00:00Z', $body); // now + 6 months, normalised
    }

    public function testNoContactYieldsNull(): void
    {
        self::assertNull(SecurityTxt::render($this->config([]), new \DateTimeImmutable('2026-07-24T09:00:00Z')));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `.build/bin/phpunit Tests/Unit/Resource/SecurityTxtTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the implementation**

```php
<?php

/*
 * This file is part of the package netresearch/nr-wellknown.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrWellknown\Resource;

use Netresearch\NrWellknown\Configuration\WellKnownConfig;

/**
 * Render an RFC 9116 security.txt. Expires is always recomputed as
 * now + expiresMonths, normalised to 00:00:00Z, so a re-generated file never lapses.
 */
final class SecurityTxt
{
    public static function render(WellKnownConfig $c, \DateTimeImmutable $now): ?string
    {
        if ($c->securityContacts() === []) {
            return null;
        }

        $expires = $now->modify(sprintf('+%d months', $c->expiresMonths()))
            ->setTime(0, 0, 0)
            ->format('Y-m-d\TH:i:s\Z');

        $lines = [];
        foreach ($c->securityContacts() as $contact) {
            $lines[] = 'Contact: ' . $contact;
        }
        $lines[] = 'Expires: ' . $expires;
        if ($c->preferredLanguages() !== []) {
            $lines[] = 'Preferred-Languages: ' . implode(', ', $c->preferredLanguages());
        }
        if ($c->securityPolicy() !== null) {
            $lines[] = 'Policy: ' . $c->securityPolicy();
        }

        return implode("\n", $lines) . "\n";
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `.build/bin/phpunit Tests/Unit/Resource/SecurityTxtTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add Classes/Resource/SecurityTxt.php Tests/Unit/Resource/SecurityTxtTest.php
git commit -S -s -m "feat(resource): render RFC 9116 security.txt with a rolling Expires"
```

---

## Task 4: `StaticResources` (gpc.json, llms.txt, agent-skills.json)

**Files:**
- Create: `Classes/Resource/StaticResources.php`, `Tests/Unit/Resource/StaticResourcesTest.php`

**Interfaces:**
- Consumes: `WellKnownConfig`.
- Produces: `gpcJson(WellKnownConfig, \DateTimeImmutable): ?string`, `llmsTxt(WellKnownConfig): ?string`, `agentSkillsJson(WellKnownConfig): ?string`. Each returns `null` when the resource should not be emitted.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Netresearch\NrWellknown\Tests\Unit\Resource;

use Netresearch\NrWellknown\Configuration\WellKnownConfig;
use Netresearch\NrWellknown\Resource\StaticResources;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Site\Entity\Site;

final class StaticResourcesTest extends TestCase
{
    private function config(array $wk): WellKnownConfig
    {
        return WellKnownConfig::fromSite(new Site('s', 1, ['base' => 'https://x.test/', 'wellknown' => $wk]));
    }

    public function testGpcOnByDefault(): void
    {
        $json = StaticResources::gpcJson($this->config([]), new \DateTimeImmutable('2026-07-24T00:00:00Z'));
        self::assertNotNull($json);
        self::assertSame(['gpc' => true, 'lastUpdate' => '2026-07-24'], json_decode($json, true));
    }

    public function testGpcDisabledYieldsNull(): void
    {
        self::assertNull(StaticResources::gpcJson($this->config(['gpc' => false]), new \DateTimeImmutable('2026-07-24T00:00:00Z')));
    }

    public function testAgentSkillsOnlyWhenNonEmpty(): void
    {
        self::assertNull(StaticResources::agentSkillsJson($this->config([])));
        $json = StaticResources::agentSkillsJson($this->config(['agentSkills' => ['skills' => [['name' => 'x']]]]));
        self::assertNotNull($json);
        self::assertStringContainsString('"name": "x"', $json);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `.build/bin/phpunit Tests/Unit/Resource/StaticResourcesTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the implementation**

```php
<?php

/*
 * This file is part of the package netresearch/nr-wellknown.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrWellknown\Resource;

use Netresearch\NrWellknown\Configuration\WellKnownConfig;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Render the static well-known resources. Each method returns null when the
 * resource must not be emitted, so a site never ships a fabricated file.
 */
final class StaticResources
{
    public static function gpcJson(WellKnownConfig $c, \DateTimeImmutable $now): ?string
    {
        if (!$c->gpcEnabled()) {
            return null;
        }

        return json_encode(
            ['gpc' => true, 'lastUpdate' => $now->format('Y-m-d')],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ) . "\n";
    }

    public static function llmsTxt(WellKnownConfig $c): ?string
    {
        $source = $c->llmsSource();
        if ($source === null) {
            return null;
        }

        // EXT:/absolute file reference, else treat the value as inline content.
        $resolved = str_contains($source, ':') || str_starts_with($source, '/')
            ? GeneralUtility::getFileAbsFileName($source)
            : '';

        if ($resolved !== '' && is_file($resolved)) {
            return (string) file_get_contents($resolved);
        }

        return rtrim($source) . "\n";
    }

    public static function agentSkillsJson(WellKnownConfig $c): ?string
    {
        $skills = $c->agentSkills();
        if ($skills === []) {
            return null;
        }

        return json_encode(['skills' => array_values($skills)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `.build/bin/phpunit Tests/Unit/Resource/StaticResourcesTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add Classes/Resource/StaticResources.php Tests/Unit/Resource/StaticResourcesTest.php
git commit -S -s -m "feat(resource): render gpc.json, llms.txt and agent-skills.json"
```

---

## Task 5: `nr:wellknown:generate` command

**Files:**
- Create: `Classes/Command/GenerateCommand.php`, `Tests/Functional/Command/GenerateCommandTest.php`

**Interfaces:**
- Consumes: `SiteFinder`, `WellKnownConfig`, `SecurityTxt`, `StaticResources`.
- Produces: the command `nr:wellknown:generate`; writes files under `Environment::getPublicPath() . '/.well-known/'` and `public/llms.txt`. Only writes a file when its renderer returns non-null; removes a stale file it would no longer emit.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Netresearch\NrWellknown\Tests\Functional\Command;

use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class GenerateCommandTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['netresearch/nr-wellknown'];

    public function testWritesSecurityTxtAndGpcButNotExcluded(): void
    {
        $this->writeSiteConfiguration('main', [
            'base'      => 'https://www.netresearch.de/',
            'rootPageId' => 1,
            'wellknown' => ['security' => ['contacts' => ['mailto:security@netresearch.de']]],
        ]);

        $command = $this->get(\Netresearch\NrWellknown\Command\GenerateCommand::class);
        (new CommandTester($command))->execute([]);

        $base = Environment::getPublicPath();
        self::assertFileExists($base . '/.well-known/security.txt');
        self::assertFileExists($base . '/.well-known/gpc.json');            // default on
        self::assertFileDoesNotExist($base . '/.well-known/openid-configuration'); // never fabricated
        self::assertStringContainsString('Contact: mailto:security@netresearch.de', (string) file_get_contents($base . '/.well-known/security.txt'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `.build/bin/phpunit -c Build/FunctionalTests.xml Tests/Functional/Command/GenerateCommandTest.php`
Expected: FAIL — command class not found.

- [ ] **Step 3: Write the implementation**

```php
<?php

/*
 * This file is part of the package netresearch/nr-wellknown.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrWellknown\Command;

use Netresearch\NrWellknown\Configuration\WellKnownConfig;
use Netresearch\NrWellknown\Resource\SecurityTxt;
use Netresearch\NrWellknown\Resource\StaticResources;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Site\SiteFinder;

#[AsCommand(
    name: 'nr:wellknown:generate',
    description: 'Write the configured well-known resources into the docroot.',
)]
final class GenerateCommand extends Command
{
    public function __construct(private readonly SiteFinder $siteFinder)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io       = new SymfonyStyle($input, $output);
        $now      = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $wellKnown = Environment::getPublicPath() . '/.well-known';
        $written  = 0;

        foreach ($this->siteFinder->getAllSites() as $site) {
            $config = WellKnownConfig::fromSite($site);
            // .well-known is filesystem-single per docroot; the first configured site wins.
            $written += $this->put($wellKnown . '/security.txt', SecurityTxt::render($config, $now));
            $written += $this->put($wellKnown . '/gpc.json', StaticResources::gpcJson($config, $now));
            $written += $this->put($wellKnown . '/agent-skills.json', StaticResources::agentSkillsJson($config));
            $written += $this->put(Environment::getPublicPath() . '/llms.txt', StaticResources::llmsTxt($config));
            if ($config->securityContacts() !== [] || $config->gpcEnabled()) {
                break; // one docroot, one configured site
            }
        }

        $io->success(sprintf('Wrote %d well-known resource(s).', $written));

        return Command::SUCCESS;
    }

    /** Write when content is non-null; delete a now-unemitted file. Returns 1 when a file was written. */
    private function put(string $path, ?string $content): int
    {
        if ($content === null) {
            if (is_file($path)) {
                @unlink($path);
            }

            return 0;
        }

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0o775, true);
        }
        file_put_contents($path, $content);

        return 1;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `.build/bin/phpunit -c Build/FunctionalTests.xml Tests/Functional/Command/GenerateCommandTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add Classes/Command/GenerateCommand.php Tests/Functional/Command/GenerateCommandTest.php
git commit -S -s -m "feat(command): nr:wellknown:generate writes the configured resources"
```

---

## Task 6: change-password middleware

**Files:**
- Create: `Classes/Middleware/ChangePasswordMiddleware.php`, `Configuration/RequestMiddlewares.php`, `Tests/Functional/Middleware/ChangePasswordMiddlewareTest.php`

**Interfaces:**
- Consumes: the request `site` attribute (resolved), `WellKnownConfig`.
- Produces: a 302 to `changePasswordTarget()` for `GET /.well-known/change-password`; passes through otherwise, and passes through (→ 404) when no target is configured.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Netresearch\NrWellknown\Tests\Functional\Middleware;

use Netresearch\NrWellknown\Middleware\ChangePasswordMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;

final class ChangePasswordMiddlewareTest extends TestCase
{
    private function handler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(\Psr\Http\Message\ServerRequestInterface $r): ResponseInterface
            {
                return new \TYPO3\CMS\Core\Http\Response('php://temp', 404);
            }
        };
    }

    public function testRedirectsWhenConfigured(): void
    {
        $site = new Site('main', 1, ['base' => 'https://www.netresearch.de/', 'wellknown' => ['changePassword' => ['target' => 'https://www.netresearch.de/passwort']]]);
        $request = (new ServerRequest('https://www.netresearch.de/.well-known/change-password', 'GET'))->withAttribute('site', $site);

        $response = (new ChangePasswordMiddleware())->process($request, $this->handler());

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('https://www.netresearch.de/passwort', $response->getHeaderLine('Location'));
    }

    public function testPassesThroughWhenUnconfigured(): void
    {
        $site = new Site('main', 1, ['base' => 'https://www.netresearch.de/']);
        $request = (new ServerRequest('https://www.netresearch.de/.well-known/change-password', 'GET'))->withAttribute('site', $site);

        self::assertSame(404, (new ChangePasswordMiddleware())->process($request, $this->handler())->getStatusCode());
    }

    public function testIgnoresOtherPaths(): void
    {
        $request = new ServerRequest('https://www.netresearch.de/kontakt', 'GET');
        self::assertSame(404, (new ChangePasswordMiddleware())->process($request, $this->handler())->getStatusCode());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `.build/bin/phpunit Tests/Functional/Middleware/ChangePasswordMiddlewareTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the middleware**

```php
<?php

/*
 * This file is part of the package netresearch/nr-wellknown.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrWellknown\Middleware;

use Netresearch\NrWellknown\Configuration\WellKnownConfig;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Answer GET /.well-known/change-password with a 302 to the site-configured
 * password-change page. Reached only because the nginx .well-known location
 * falls through to TYPO3 for absent files. Any other path passes through.
 */
final class ChangePasswordMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getUri()->getPath() !== '/.well-known/change-password') {
            return $handler->handle($request);
        }

        $site = $request->getAttribute('site');
        if ($site instanceof Site) {
            $target = WellKnownConfig::fromSite($site)->changePasswordTarget();
            if ($target !== null) {
                return new RedirectResponse($target, 302);
            }
        }

        return $handler->handle($request);
    }
}
```

- [ ] **Step 4: Write `Configuration/RequestMiddlewares.php`** (after site resolution, so the `site` attribute exists)

```php
<?php

/*
 * This file is part of the package netresearch/nr-wellknown.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use Netresearch\NrWellknown\Middleware\ChangePasswordMiddleware;

return [
    'frontend' => [
        'netresearch/nr-wellknown/change-password' => [
            'target' => ChangePasswordMiddleware::class,
            'after'  => ['typo3/cms-frontend/site'],
            'before' => ['typo3/cms-frontend/base-redirect-resolver'],
        ],
    ],
];
```

- [ ] **Step 5: Run test to verify it passes**

Run: `.build/bin/phpunit Tests/Functional/Middleware/ChangePasswordMiddlewareTest.php`
Expected: PASS (3 tests).

- [ ] **Step 6: Full suite + static analysis, then commit**

Run: `.build/bin/phpunit && .build/bin/phpstan analyse Classes --level max`
Expected: all green.

```bash
git add Classes/Middleware/ChangePasswordMiddleware.php Configuration/RequestMiddlewares.php Tests/Functional/Middleware/ChangePasswordMiddlewareTest.php
git commit -S -s -m "feat(middleware): redirect /.well-known/change-password to the configured page"
```

---

## Task 7: Docs

**Files:**
- Create: `README.rst`, `AGENTS.md`

- [ ] **Step 1: Write `README.rst`** — what it serves, the `wellknown` site-config schema (copy §4.2 of the spec verbatim), the deploy-time `nr:wellknown:generate` wiring, and the hard dependency on the nginx `.well-known` fallthrough (Task 8). State plainly that the 14 excluded resources are intentionally 404.

- [ ] **Step 2: Write `AGENTS.md`** — verified commands only (`composer install`, `.build/bin/phpunit`, `.build/bin/phpstan analyse Classes`), the file map, and the rule "never fabricate an excluded resource".

- [ ] **Step 3: Commit**

```bash
git add README.rst AGENTS.md
git commit -S -s -m "docs: document configuration, generation and the nginx dependency"
```

---

## Task 8: t3re nginx fallthrough (separate repo)

**Files:**
- Modify: `netresearch/t3re` `rootfs/etc/nginx/conf.d/default.conf` (the `location ^~ /.well-known/` block)

**Interfaces:**
- Produces: absent `.well-known` paths fall through to TYPO3, so the change-password middleware is reachable; static files still served directly.

- [ ] **Step 1: Change the block**

From:
```nginx
location ^~ /.well-known/ {
    allow all;
}
```
to:
```nginx
location ^~ /.well-known/ {
    # Serve a present static file directly; fall through to TYPO3 for absent
    # paths so nr_wellknown can answer /.well-known/change-password. NRNR-1578.
    try_files $uri $uri/ @t3frontend;
}
```

- [ ] **Step 2: Open the MR** via `glab api -X POST "projects/4018/merge_requests" -H "Content-Type: application/json"` (the CLI form aborts outside a checkout). The pipeline's `test-ping` / `traefik`-equivalent healthcheck (or the repo's config-parse gate) validates the change. Do **not** merge without sign-off — this is a fleet runtime.

- [ ] **Step 3: Verify** on the branch that the block reads back exactly as intended (re-fetch the raw file) and that the diff is that block only.

---

## Task 9: netresearch.de application (separate repo)

**Files:**
- Modify: `netresearch/netresearch` `config/sites/main/config.yaml`, the deploy pipeline

**Interfaces:**
- Consumes: the released extension + the t3re fallthrough.

- [ ] **Step 1: `composer require netresearch/nr-wellknown`** in the site repo.

- [ ] **Step 2: Add the `wellknown` block** to `config/sites/main/config.yaml` with the real values — security contact, policy URL, preferred languages, `changePassword.target` (only if netresearch.de has a frontend password-change page; otherwise omit and it 404s correctly, per spec §10), and the llms.txt source.

- [ ] **Step 3: Wire `nr:wellknown:generate` into the deploy** so the docroot files are (re)written on every deploy, refreshing security.txt's Expires. Confirm the command runs after the docroot is in place.

- [ ] **Step 4: Commit + MR** on the site repo; deploy via the manual/weekly Concourse trigger (netresearch.de deploys do not auto-run on push).

---

## Task 10: Acceptance

- [ ] **Step 1:** After the runtime and the site deploy, run the netresearch.de census (trigger the report pipeline).
- [ ] **Step 2: Verify** in `results.json`: the 5 in-scope criteria are `met`, the 14 excluded stay `not_applicable`, and `well-known-uris.well-known-uris` no longer says "blanket-blocked".
- [ ] **Step 3:** `curl -sI https://www.netresearch.de/.well-known/security.txt` → 200 with a future `Expires`; `curl -sI https://www.netresearch.de/.well-known/change-password` → 302 (or a clean 404 if intentionally unconfigured).
- [ ] **Step 4:** Note the fleet opt-in path (require extension + site config) in the extension README; nothing else is fleet-automatic.
