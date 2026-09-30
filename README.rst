.. SPDX-License-Identifier: GPL-2.0-or-later
.. SPDX-FileCopyrightText: Netresearch DTT GmbH

============================
Netresearch: Well-Known
============================

Serve the well-known resources a TYPO3 site should provide, from per-site
configuration. Static content is written into the docroot and served by the web
server; the one redirect (``change-password``) is answered by a PSR-15
middleware.

What it provides
================

Only the resources a corporate site genuinely should serve, each emitted **only
when configured**:

===============================  ==================================  ============
Resource                         Path                                Kind
===============================  ==================================  ============
Security contact (RFC 9116)      ``/.well-known/security.txt``       static
Password-change hint             ``/.well-known/change-password``    redirect
Global Privacy Control           ``/.well-known/gpc.json``           static
Agent guidance                   ``/llms.txt``                       static
Agent skills discovery           ``/.well-known/agent-skills.json``  static
===============================  ==================================  ============

Resources this extension deliberately does **not** create — OAuth, WebAuthn,
NodeInfo/fediverse, ``apple-app-site-association``, ``api-catalog``, IndexNow and
the other agent-readiness documents — are genuinely not-applicable to a site that
does not offer them. A ``404`` is the correct answer there; do not fabricate them.

Scope: frontend only, never the backend
=======================================

Well-known URIs describe the site to its **visitors** — the frontend. Nothing this
extension serves may reference the TYPO3 backend:

- ``change-password`` targets the page where a **frontend user** (``fe_users`` —
  member area, customer portal) changes their password. It must **never** point at
  the backend login (``/typo3``): a well-known URI advertising the admin login is
  an invitation, not a service. The extension has no default target — a site
  without frontend accounts simply leaves ``changePassword`` unconfigured and the
  path answers ``404``, which is the correct, honest response.
- The same rule applies to every other resource: content is site/visitor-facing
  configuration, never backend URLs, backend users or internal infrastructure.

Requirements on the web server
==============================

The static files must be reachable under ``/.well-known/`` and absent paths must
fall through to TYPO3 so the ``change-password`` middleware is reached. In the
Netresearch ``t3re`` runtime the nginx block must read::

    location ^~ /.well-known/ {
        try_files $uri $uri/ @t3frontend;
    }

A block that only ``allow all`` serves static files but 404s absent paths before
PHP — the ``change-password`` redirect would never be reached.

Configuration
=============

Per site, in ``config/sites/<site>/config.yaml`` under a ``wellknown`` key. Every
value is optional; a resource is emitted only when its required fields are set::

    wellknown:
      security:
        contacts: ["mailto:security@netresearch.de"]   # >=1 required to emit
        policy: "https://www.netresearch.de/security-policy"
        preferredLanguages: ["de", "en"]
        expiresMonths: 6                               # default 6, minimum 1
      changePassword:
        target: "https://www.netresearch.de/mein-konto/passwort"  # required to emit
      gpc: true                                         # default true
      llms:
        source: "EXT:sitepackage/Resources/Public/llms.txt"  # file ref or inline text
      agentSkills:
        skills: []                                      # emitted only if non-empty

Generation and refresh
======================

Run at deploy time::

    vendor/bin/typo3 nr:wellknown:generate

This writes the configured files into ``public/.well-known/`` (and ``public/llms.txt``)
and recomputes ``security.txt``'s ``Expires`` as *now + expiresMonths*, where
``expiresMonths`` is floored at 1 so a value of ``0`` or a negative one cannot
produce an already-expired file. Re-running on every deploy keeps ``Expires``
valid, so the file never silently lapses. A site that deploys rarely should add
a Scheduler task or CI cron invoking the same command. The generated files carry a moving date and are **not** committed to git.

Acceptance
==========

Verify with the Website-Specification conformance checker: the in-scope criteria
flip to *met*, the out-of-scope ones stay correctly *not applicable*, and
``curl -sI https://<host>/.well-known/security.txt`` returns ``200`` with a future
``Expires``.

Architecture and security
=========================

`docs/SECURITY-ASSURANCE.md <https://github.com/netresearch/t3x-nr-wellknown/blob/main/docs/SECURITY-ASSURANCE.md>`__ describes the
components, actors and data flows, what the extension does and does not
guarantee in terms of security, its trust boundaries, and how it counters common
weaknesses.

Governance and policies
=======================

This extension follows the organisation-wide Netresearch policies:

- `Governance <https://github.com/netresearch/.github/blob/main/GOVERNANCE.md>`__: ownership, roles, how decisions are made and conflicts resolved.
- `Roadmap <https://github.com/netresearch/.github/blob/main/ROADMAP.md>`__: planned and excluded work for the next twelve months.
- `Handling of dependency and code analysis findings <https://github.com/netresearch/.github/blob/main/SECURITY.md#handling-of-dependency-and-code-analysis-findings>`__: which vulnerability, licence and static-analysis findings must be fixed, by when, and how exceptions are recorded.
- `Secret management <https://github.com/netresearch/.github/blob/main/SECURITY.md#secret-management>`__: where CI and release credentials are stored, who may use them, how committed secrets are detected, and when secrets are rotated.
- `Access roster <https://github.com/netresearch/.github/blob/main/docs/access-roster.md>`__: the people and teams with administrative or write access to this repository.

Checks that run on every pull request in this repository:

- ``.github/workflows/checks.yml``: Composer Audit (fails on any advisory for an installed package; ``composer.json`` lists no ``config.audit.ignore`` exceptions) and Opengrep SAST (fails a pull request as the `organisation rule <https://github.com/netresearch/.github/blob/main/SECURITY.md#static-analysis-sast>`__ sets out), both through ``typo3-ci-workflows``' ``security.yml``; Dependency Review (fails on newly added dependencies with a vulnerability of severity high or higher); PHP License Audit (``license-check.yml``, fails on an SSPL or BSL licensed Composer dependency); CodeQL with language auto-detection, which finds no JavaScript or Go here and analyses the workflow files (CodeQL has no PHP analysis; PHPStan and Opengrep cover the PHP code); Betterleaks secret scanning; zizmor for the workflow files (reported to code scanning, not blocking); ``pr-quality`` (the pull request size check, and the automatic approval of pull requests that maintainers open); the aggregate gate ``All security checks``, which fails when any of these jobs fails. The fuzz job finds no ``Build/phpunit.xml`` and is skipped. The OpenSSF Scorecard job runs only on pushes to ``main`` and on the weekly schedule.
- ``.github/workflows/ci.yml``: PHP lint on PHP 8.2 to 8.5; code style (PHP-CS-Fixer, ``Build/.php-cs-fixer.dist.php``) and Rector (``Build/rector.php``) on PHP 8.2; PHPStan (level 10, ``Build/phpstan.neon``), the advisory ``PHPStan (unpinned PHPUnit)`` job, which runs PHPStan once more against the newest PHPUnit, unit tests and functional tests (SQLite) on PHP 8.2 to 8.5 with TYPO3 ^12.4, ^13.4 and ^14.3. Fractor is not part of the CI run, and there is no ``Documentation/`` directory to render. The aggregate gate ``ci / All CI checks`` fails when any of these jobs fails.
