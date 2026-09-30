<!-- SPDX-License-Identifier: GPL-2.0-or-later -->
<!-- SPDX-FileCopyrightText: Netresearch DTT GmbH -->
# Architecture and security assurance

This document describes the architecture of `nr_wellknown`, what users can and cannot expect from it in terms of security, its threat model and trust boundaries, and how it counters common weaknesses. Every statement refers to the code at the commit that contains this file. Vulnerabilities are reported as described in the organisation's [security policy](https://github.com/netresearch/.github/blob/main/SECURITY.md).

Where a statement depends on TYPO3 itself, it was checked against TYPO3 14.3.7, the version Composer resolved for `typo3/cms-core: ^12.4 || ^13.4 || ^14.3` on 2026-09-30 (the repository tracks no `composer.lock`).

## Architecture

### Actors

| Actor | What it does | How it reaches the extension |
|-------|--------------|------------------------------|
| Integrator | Writes the `wellknown` key of a site configuration | `config/sites/<site>/config.yaml` |
| Operator or deployment job | Runs `vendor/bin/typo3 nr:wellknown:generate` | TYPO3 CLI on the host |
| Web server | Serves the generated files from the docroot and hands absent `/.well-known/` paths to TYPO3 | Web server configuration (see README.rst, "Requirements on the web server") |
| Visitor or client (browser, password manager, crawler, AI agent) | Requests the well-known URIs | HTTP |

### Components

| Component | Role |
|-----------|------|
| `Classes/Configuration/WellKnownConfig.php` | Reads the `wellknown` key of one site into an immutable object; keeps only values of the expected type and falls back to defaults otherwise |
| `Classes/Resource/SecurityTxt.php` | Renders `security.txt` (RFC 9116) or returns `null` when no contact is configured |
| `Classes/Resource/StaticResources.php` | Renders `gpc.json`, `llms.txt` and `agent-skills.json`, each `null` when not configured |
| `Classes/Command/GenerateCommand.php` | Console command `nr:wellknown:generate`: writes the rendered files into the public directory and deletes files whose resource is no longer configured |
| `Classes/Middleware/ChangePasswordMiddleware.php` | PSR-15 middleware answering `/.well-known/change-password` with a redirect |
| `Configuration/Services.yaml` | Dependency injection; registers the command |
| `Configuration/RequestMiddlewares.php` | Registers the middleware in the frontend stack after `typo3/cms-frontend/site` and before `typo3/cms-frontend/base-redirect-resolver` |

The extension has no database tables, no backend module, no frontend plugin, no scheduler task and no network client.

### Paths served

| Path | Produced by | Served by | Emitted when |
|------|-------------|-----------|--------------|
| `/.well-known/security.txt` | `SecurityTxt::render()` | Web server (static file) | `security.contacts` has at least one string |
| `/.well-known/gpc.json` | `StaticResources::gpcJson()` | Web server (static file) | `gpc` is not false (default true) |
| `/.well-known/agent-skills.json` | `StaticResources::agentSkillsJson()` | Web server (static file) | `agentSkills.skills` is a non-empty list |
| `/llms.txt` | `StaticResources::llmsTxt()` | Web server (static file) | `llms.source` is set |
| `/.well-known/change-password` | `ChangePasswordMiddleware` | TYPO3 frontend request | `changePassword.target` is set; otherwise the request passes on and TYPO3 answers as for any unknown path |

### Data flows

1. Generation (CLI): `config/sites/*/config.yaml` → TYPO3 `SiteFinder::getAllSites()` → `WellKnownConfig::fromSite()` of the **first** site (the loop in `GenerateCommand::execute()` ends after one site, because a docroot has one `.well-known/` directory) → the renderers → `GenerateCommand::put()` → files under `Environment::getPublicPath()`. For `llms.txt`, a `llms.source` value that contains `:` or starts with `/` is resolved with `GeneralUtility::getFileAbsFileName()` and, if it names a file, that file's content is published; otherwise the value itself is published.
2. Serving static files: visitor → web server → file in the docroot. The extension's code does not run for these requests.
3. Redirect: visitor → web server → TYPO3 frontend → site resolution → `ChangePasswordMiddleware::process()` → `302` with `Location` set to the configured target.

## Security expectations

What you can expect:

- Only the five resources above are created, and each only when its configuration is present. Absent configuration produces no file, and `nr:wellknown:generate` deletes a previously generated file whose resource is no longer configured (`GenerateCommand::put()`).
- No file-system path is derived from an HTTP request. The command writes only the four fixed file names appended to `Environment::getPublicPath()`. The middleware compares the request path with the literal `/.well-known/change-password` and reads no file.
- The redirect target comes only from the site configuration; query parameters and headers of the request do not influence it.
- `security.txt` always carries an `Expires` date in the future at generation time: now plus `expiresMonths`, which is floored at 1 (`WellKnownConfig::expiresMonths()`).
- JSON documents are produced by `json_encode()` with `JSON_THROW_ON_ERROR`; a value that cannot be encoded makes the command fail rather than write a broken file.

What you cannot expect:

- No validation of configured values. Contacts, policy URL, languages and the redirect target are written as configured. A value containing a line break adds a line to `security.txt`, and the redirect target may be any URL, including the TYPO3 backend login, which README.rst says it must never be. The site configuration is trusted input.
- No restriction of what `llms.source` publishes beyond TYPO3's path rules: `getFileAbsFileName()` accepts `EXT:` references and absolute paths below the project path (or `BE.lockRootPath`) without `..`, and relative paths below the public directory. Any file there that the CLI user can read is published verbatim. Point `llms.source` only at content meant to be public.
- No signing of `security.txt` (RFC 9116 permits an OpenPGP signature; the renderer does not produce one) and no `Canonical` field.
- One set of files per docroot: in an installation with several sites, only the first site's configuration is used for the generated files. The redirect uses the configuration of the site TYPO3 resolved for the request.
- The redirect answers every request method on that path, not only `GET`.
- `Expires` stays valid only if the command is re-run before the date passes; the extension does not schedule itself.
- Content types, caching headers, TLS and access control for the generated files are the web server's responsibility.

## Threat model and trust boundaries

The extension handles no secrets, no personal data and no authentication. What it protects is the integrity of the published documents and of the redirect: an attacker who could change them could redirect visitors to a phishing page or publish a false security contact.

| Boundary | Untrusted input | Control |
|----------|-----------------|---------|
| Visitor → web server → middleware | Request path, query parameters, headers, method | The middleware reacts only to the exact path `/.well-known/change-password`; it takes the target from the resolved site's configuration and nothing else from the request (`Tests/Unit/Middleware/ChangePasswordMiddlewareTest.php`, `testRedirectTargetIsTakenFromSiteConfigurationNotFromTheRequest`, `testIgnoresOtherPaths`) |
| Visitor → web server → static files | Request path | Handled by the web server; the extension's code does not run |
| Integrator → extension | Site configuration YAML | Trusted. `WellKnownConfig::fromSite()` reads each value with the type it expects: non-string contacts and languages are dropped, a non-numeric `expiresMonths` falls back to 6, `gpc` is cast to a boolean, and a section that is not a map counts as absent; `agentSkills.skills` is passed on as configured (`Tests/Unit/Configuration/WellKnownConfigTest.php` covers the defaults) |
| Operator → CLI command | Invocation of `nr:wellknown:generate` | Trusted: whoever runs the TYPO3 CLI already has file-system access to the docroot. The command takes no arguments or options |
| Installed extensions and project files → `llms.txt` | File named by `llms.source` | Trusted: resolved by `GeneralUtility::getFileAbsFileName()` as described above |

## Secure design principles applied

- Fail-safe defaults: a resource without its required configuration is not emitted, and the change-password path passes on to TYPO3 when no target is configured (`testPassesThroughWhenUnconfigured`). `security.contacts` and `changePassword.target` have no default, so a site without a `wellknown` key gets only `gpc.json`.
- Economy of mechanism: static files are generated once and served by the web server without PHP. At request time the extension adds one string comparison to each frontend request that reaches TYPO3, and a configuration lookup on the change-password path.
- Least privilege: the frontend request path never writes to the file system; writing happens only in the CLI command.
- Input is data, never code or paths: no value from a request is used as a file name, a URL or an argument to a shell or database call.

## Countering common weaknesses

| Weakness (CWE / OWASP) | Counter | Evidence |
|------------------------|---------|----------|
| Open redirect (CWE-601, A01:2021) | The `Location` value is the configured target only | `ChangePasswordMiddleware::process()`; `testRedirectTargetIsTakenFromSiteConfigurationNotFromTheRequest` |
| Path traversal (CWE-22), arbitrary file write or delete (CWE-73) | File names are literals appended to `Environment::getPublicPath()`; no request data reaches `file_put_contents()` or `unlink()` | `GenerateCommand::execute()`, `GenerateCommand::put()` |
| Injection into JSON output (CWE-116) | `json_encode()` escapes every value; encoding errors throw | `StaticResources::gpcJson()`, `StaticResources::agentSkillsJson()`; `Tests/Unit/Resource/StaticResourcesTest.php` |
| Cross-site scripting (CWE-79, A03:2021) | No HTML is produced; the generated files are plain text and JSON served by the web server | `Classes/Resource/` |
| Improper input validation (CWE-20) | Configuration values are type-checked when read; `expiresMonths` is floored at 1 | `WellKnownConfig::fromSite()`, `WellKnownConfig::expiresMonths()` |
| Hard-coded credentials (CWE-798) | None in the code; Betterleaks scans every pull request | `.github/workflows/checks.yml` |
| Vulnerable and outdated components (A06:2021) | Composer Audit and Dependency Review on every pull request, Renovate update pull requests | `.github/workflows/checks.yml`, `renovate.json` |

## Verification

The checks listed in README.rst under "Governance and policies" run on every pull request: PHPStan at level 10, Opengrep, CodeQL for the workflow files, Composer Audit, Dependency Review, Betterleaks, zizmor, and the unit and functional test suites. Findings are handled as described in the organisation's [policy for dependency and code analysis findings](https://github.com/netresearch/.github/blob/main/SECURITY.md#handling-of-dependency-and-code-analysis-findings).

Update this document when a resource, an entry point or a trust assumption changes.
