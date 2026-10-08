<!-- SPDX-License-Identifier: GPL-2.0-or-later -->
<!-- SPDX-FileCopyrightText: Netresearch DTT GmbH -->
<!-- FOR AI AGENTS - Human readability is a side effect, not a goal -->

# AGENTS.md

**What this is:** `netresearch/nr-wellknown` — a TYPO3 v12.4/v13.4/v14.3 extension that serves the
well-known resources a site should provide (security.txt, change-password, gpc.json, llms.txt,
agent-skills.json) from per-site configuration. Static content is generated into the docroot; the
one redirect (change-password) is a PSR-15 middleware.

**Design:** `docs/superpowers/specs/2026-07-24-static-wellknown-typo3-design.md`.
**Architecture and security:** `docs/SECURITY-ASSURANCE.md` (update it when a resource, an entry point or a trust assumption changes).

## The one rule that matters

**Never fabricate an excluded resource.** 14 of the ~22 probed well-known criteria are genuinely
not-applicable to a site that does not offer OAuth, WebAuthn, a fediverse endpoint, an iOS app, a
public API or IndexNow. A 404 is the correct answer there. Only the 5 in-scope resources are
provisioned, and each only when its required config is present.

## Commands (verified 2026-09-30)

> The toolchain lives in `.build/` (composer `bin-dir`). Run `composer install` once.

| Task | Composer script (host PHP) | Shared runner in Docker |
|------|----------------------------|-------------------------|
| Install | `composer install` | |
| PHP lint | `composer ci:test:php:lint` (`phplint`, `Build/.phplint.yml`) | `./Build/Scripts/runTests.sh -s lint` |
| Code style | `composer ci:test:php:cgl` (dry run, `Build/.php-cs-fixer.dist.php`) | `./Build/Scripts/runTests.sh -s cgl -n` |
| Static analysis | `composer ci:test:php:phpstan` (level 10, `Build/phpstan.neon`) | `./Build/Scripts/runTests.sh -s phpstan` |
| Rector | `composer ci:test:php:rector` (dry run) | `./Build/Scripts/runTests.sh -s rector -n` |
| Fractor | `composer ci:test:php:fractor` (dry run) | `./Build/Scripts/runTests.sh -s fractor -n` |
| Unit tests | `composer ci:test:php:unit` (`Build/UnitTests.xml`) | `./Build/Scripts/runTests.sh -s unit` |
| Functional tests | `typo3DatabaseDriver=pdo_sqlite composer ci:test:php:functional` (`Build/FunctionalTests.xml`) | `./Build/Scripts/runTests.sh -s functional -d sqlite` |

Without `-n`, the runner's `cgl`, `rector` and `fractor` suites rewrite files; `composer ci:cgl`, `ci:rector` and `ci:fractor` do the same.

`Build/Scripts/runTests.sh` is the bootstrap stub of `netresearch/typo3-ci-workflows`; the runner comes from the package and is linked into `.build/bin`. It runs the suites in Docker against a chosen PHP version (`-p 8.5`; use the PHP version the dependencies were installed with) and picks up `Build/UnitTests.xml`, `Build/FunctionalTests.xml`, `Build/phpstan.neon`, `Build/rector.php`, `Build/fractor.php` and `Build/.php-cs-fixer.dist.php`, reporting a notice for each non-standard location. Its `-s lint` runs `php -l` over every PHP file outside the generated directories (`.build`, `public`, `var` and others).

CI (`.github/workflows/ci.yml`) runs lint, code style, PHPStan, Rector, unit and functional tests (SQLite) through `netresearch/typo3-ci-workflows`; it does not run Fractor. `.github/workflows/checks.yml` runs the security checks listed in README.rst under "Governance and policies".

## File map

```
Classes/Configuration/WellKnownConfig.php   → immutable per-site config DTO (fromSite + defaults)
Classes/Resource/SecurityTxt.php            → RFC 9116 security.txt, rolling Expires
Classes/Resource/StaticResources.php        → gpc.json, llms.txt, agent-skills.json
Classes/Command/GenerateCommand.php         → nr:wellknown:generate, writes the docroot files
Classes/Middleware/ChangePasswordMiddleware.php → 302 for /.well-known/change-password
Configuration/Services.yaml                 → DI (autowire, command autoconfigure)
Configuration/RequestMiddlewares.php        → registers the middleware after site resolution
Tests/Unit, Tests/Functional                → mirror Classes/
```

## Boundaries

- **Frontend only, never the backend.** Well-known URIs describe the site to its visitors.
  `change-password` targets the fe_users password page and must never point at `/typo3` — a
  well-known URI advertising the backend login is an invitation. No resource may carry backend
  URLs, backend users or internal infrastructure. A site without frontend accounts leaves
  `changePassword` unset; the 404 is correct.
- **Always** add a test for a new resource or config value; a renderer returns `null` when its
  resource must not be emitted (so the command writes nothing).
- **Never** commit generated well-known files — they carry a moving `Expires`.
- **Ask first** before widening the scope beyond the 5 in-scope resources.
- Commits: Conventional Commits, signed + DCO (`git commit -S -s`).

## Not yet done

PHPStan runs at level 10 with the ergebnis rules (`Build/phpstan.neon`), and CI runs Rector. Not
wired up: Fractor in CI, architecture rules (phpat is installed but no rule is defined), mutation
testing. The cross-repo steps — the t3re nginx
`try_files` line and the netresearch.de site config + deploy wiring — are Tasks 8–9 of the plan
and need sign-off.
