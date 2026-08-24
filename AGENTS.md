<!-- FOR AI AGENTS - Human readability is a side effect, not a goal -->

# AGENTS.md

**What this is:** `netresearch/nr-wellknown` — a TYPO3 v13.4/v14 extension that serves the
well-known resources a site should provide (security.txt, change-password, gpc.json, llms.txt,
agent-skills.json) from per-site configuration. Static content is generated into the docroot; the
one redirect (change-password) is a PSR-15 middleware.

**Design:** `docs/superpowers/specs/2026-07-24-static-wellknown-typo3-design.md`.
**Plan:** `docs/superpowers/plans/2026-07-24-nr-wellknown-implementation.md`.

## The one rule that matters

**Never fabricate an excluded resource.** 14 of the ~22 probed well-known criteria are genuinely
not-applicable to a site that does not offer OAuth, WebAuthn, a fediverse endpoint, an iOS app, a
public API or IndexNow. A 404 is the correct answer there. Only the 5 in-scope resources are
provisioned, and each only when its required config is present.

## Commands (verified 2026-07-24)

> The toolchain lives in `.build/` (composer `bin-dir`). Run `composer install` once.

| Task | Command |
|------|---------|
| Install | `composer install` |
| Unit tests | `.build/bin/phpunit -c .build/vendor/typo3/testing-framework/Resources/Core/Build/UnitTests.xml Tests/Unit` |
| Functional tests | `typo3DatabaseDriver=pdo_sqlite .build/bin/phpunit -c .build/vendor/typo3/testing-framework/Resources/Core/Build/FunctionalTests.xml Tests/Functional` |
| Static analysis | `.build/bin/phpstan analyse Classes --level=8` |
| Code style | `.build/bin/php-cs-fixer fix Classes Tests --rules=@PSR12 --dry-run --diff` |
| Any suite in Docker (verified 2026-08-24) | `./Build/Scripts/runTests.sh -s unit\|functional\|lint\|phpstan\|rector\|cgl` |

Functional tests run against sqlite in this environment.

`Build/Scripts/runTests.sh` is the bootstrap stub of `netresearch/typo3-ci-workflows`; the runner comes from the package and is linked into `.build/bin`. It runs the suites in Docker against a chosen PHP version (`-p 8.2`) and picks up `Build/UnitTests.xml`, `Build/FunctionalTests.xml`, `Build/phpstan.neon`, `Build/rector.php` and `Build/.php-cs-fixer.dist.php` — the last three from non-standard locations, which it reports as a notice. It has no `fractor` suite, and its `-s lint` runs `php -l` over `Classes Configuration Tests` rather than `phplint` with `Build/.phplint.yml`, so it does not cover `ext_emconf.php` (netresearch/typo3-ci-workflows#217).

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
- Commits: Conventional Commits, signed + DCO (`git commit -S -s`), no AI/bot attribution.

## Not yet done

The full Netresearch CI scaffold (`Build/` level-10 PHPStan with ergebnis + phpat, GitLab CI,
Rector/Fractor, mutation testing) is not wired up. Adopt it via the `skill-repo` /
`enterprise-readiness` conventions before release. The cross-repo steps — the t3re nginx
`try_files` line and the netresearch.de site config + deploy wiring — are Tasks 8–9 of the plan
and need sign-off.
