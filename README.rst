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
        expiresMonths: 6                               # default 6
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
and recomputes ``security.txt``'s ``Expires`` as *now + expiresMonths*. Re-running
on every deploy keeps ``Expires`` valid, so the file never silently lapses. A site
that deploys rarely should add a Scheduler task or CI cron invoking the same
command. The generated files carry a moving date and are **not** committed to git.

Acceptance
==========

Verify with the Website-Specification conformance checker: the in-scope criteria
flip to *met*, the out-of-scope ones stay correctly *not applicable*, and
``curl -sI https://<host>/.well-known/security.txt`` returns ``200`` with a future
``Expires``.
