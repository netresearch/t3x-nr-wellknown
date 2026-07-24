# Design Spec — Static well-known provisioning for TYPO3 (`nr_wellknown`)

- **Date:** 2026-07-24
- **Status:** Draft for review
- **Author:** Sebastian Mendel
- **Related:** [[NRNR-1578]] (the nginx deny-rule fix that unblocks `/.well-known/`),
  NRS-4575 (apex→www redirect), the Website-Specification conformance checker
  (`support/website-spec-checker`) which measures the acceptance criteria.

## 1. Context & goal

The Website-Specification conformance checker probes ~22 `.well-known` and related URIs.
On www.netresearch.de every one returned **403** — a single nginx rule (`location ~ /\.`)
blanket-blocked the whole `.well-known/` tree. That rule was exempted fleet-wide in NRNR-1578,
so the paths now reach the docroot instead of 403ing. **Unblocking is only half the job:** once
reachable, most of these URIs still return **404**, because nothing serves them.

**Goal:** a generic, fleet-reusable way for TYPO3 instances to serve the well-known resources a
corporate site genuinely should serve — with per-site content — and apply it to netresearch.de.

**Non-goals:** inventing resources a site does not actually provide. 14 of the 22 criteria are
genuinely **not applicable** to netresearch.de (OAuth server, WebAuthn, fediverse/NodeInfo,
apple-app-site-association, IndexNow, api-catalog …). For those a **404 is the correct answer** —
this spec deliberately does not fabricate them.

## 2. Scope

Provide exactly these, chosen as the set that applies to a corporate TYPO3 site (measured
2026-07-23 as `not_met` on netresearch.de, i.e. the site should serve them but doesn't):

| Resource | Path | Kind | Criterion |
|---|---|---|---|
| Security contact | `/.well-known/security.txt` | static, refreshed | `security.well-known-security-txt` |
| Password-change hint | `/.well-known/change-password` | **redirect** | `well-known-uris.well-known-change-password` |
| Global Privacy Control | `/.well-known/gpc.json` | static | `privacy.global-privacy-control-gpc` |
| Agent guidance | `/llms.txt` | static | (agent-readiness) |
| Agent skills discovery | `/.well-known/agent-skills.json` | static | `agent-readiness.agent-skills-discovery` |

Note: `well-known-uris.well-known-uris` (the "blanket-blocked" verdict) is cleared by the
NRNR-1578 deny-fix alone — that is a *reachability* check, not a content check. This extension is
what flips the individual per-resource criteria above from `not_met` to `met`.

**Explicitly excluded** (correct as 404 / not-applicable, do not fabricate):
`well-known-oauth-authorization-server`, `well-known-oauth-protected-resource`,
`well-known-openid-configuration`, `well-known-webauthn`, `well-known-assetlinks-json`,
`well-known-nodeinfo`, `well-known-webfinger`, `well-known-traffic-advice`,
`well-known-apple-app-site-association`, `well-known-api-catalog`, `seo.indexnow`,
`a2a-agent-cards`, `agentic-resource-discovery-ard`, `mcp-and-tool-discovery`,
`nlweb-conversational-interface-discovery`, `schemamap-discoverable-json-ld-endpoints`,
`web-bot-auth-verifiable-bot-identity`.

## 3. The load-bearing constraint: how nginx routes `.well-known`

The merged NRNR-1578 block (`netresearch/t3re` `rootfs/etc/nginx/conf.d/default.conf:166-168`) is:

```nginx
location ^~ /.well-known/ {
    allow all;
}
```

A `^~` prefix location with only `allow all` and no `try_files`/`fastcgi_pass` uses nginx's
default **static-file handler** rooted at `/var/www/html`. Consequences, verified against the
merged file:

- A **physical file** at `public/.well-known/<name>` is served directly by nginx — fast, no PHP.
- An **absent** path returns **404 from nginx** and never reaches TYPO3 (regular traffic goes via
  `@t3frontend` at `:102`; this block does not fall through to it).

Therefore: static content works as-is, but a **redirect** (`change-password`) cannot be a static
file. This spec adds one line so absent paths fall through to TYPO3:

```nginx
location ^~ /.well-known/ {
    try_files $uri $uri/ @t3frontend;
}
```

Static files still win (nginx serves them before the fallthrough); only absent paths reach
TYPO3, where the extension's middleware handles the redirect. This is one line in the same t3re
block NRNR-1578 already touched, and it also future-proofs any later dynamic well-known.

## 4. Architecture

Two layers, cleanly split into *generic mechanism* and *per-site content*.

### 4.1 Generic: the extension `netresearch/nr-wellknown` (key `nr_wellknown`)

A small TYPO3 v13/v14 extension, composer-installable fleet-wide.

**Console command `wellknown:generate`** — reads the site configuration and writes the static
files into each site's docroot `public/.well-known/` (and `public/llms.txt`). Idempotent; safe to
run on every deploy. Writes only the resources that are configured/enabled; never creates the
excluded ones.

**PSR-15 middleware** (frontend, before routing) — handles the one dynamic case:
`GET /.well-known/change-password` → **302** to the configured target. Reached only because of the
`try_files` fallthrough (§3). The middleware short-circuits and does not bootstrap the full
frontend for this path. It handles *only* `change-password`; all other well-known paths are static
files served by nginx.

**Defaults** — an unconfigured install still produces a valid `gpc.json` (`{"gpc":true}`); it does
**not** emit `security.txt` or `change-password` until configured, so a site never ships an empty
or misleading security contact.

### 4.2 Per-site: TYPO3 site configuration

All values live in each site's `config/sites/<site>/config.yaml` under a `wellknown` key — no code
per site. Schema (all optional; a resource is emitted only when its required fields are present):

```yaml
wellknown:
  security:
    contacts: ["mailto:security@netresearch.de"]   # RFC 9116 Contact (>=1 required)
    policy: "https://www.netresearch.de/security-policy"   # optional
    preferredLanguages: ["de", "en"]               # optional
    expiresMonths: 6                               # default 6
  changePassword:
    target: "https://www.netresearch.de/mein-konto/passwort"  # required to emit
  gpc: true                                         # default true
  llms:
    source: "EXT:sitepackage/Resources/Public/llms.txt"  # or inline text
  agentSkills:
    skills: []                                      # emitted only if non-empty
```

## 5. Resource formats

**`security.txt`** (RFC 9116, `text/plain; charset=utf-8`):
```
Contact: mailto:security@netresearch.de
Expires: 2027-01-24T00:00:00Z
Preferred-Languages: de, en
Policy: https://www.netresearch.de/security-policy
```
`Expires` is computed at generation time as `now + expiresMonths`, normalised to `00:00:00Z`.
Not PGP-signed in this iteration (RFC 9116 SHOULD, deferred — see §9).

**`change-password`** — 302 redirect, no body. Target from `wellknown.changePassword.target`.
Omitted entirely (→ 404, correct) when unconfigured, e.g. a site with no frontend user accounts.

**`gpc.json`** (`application/json`): `{"gpc": true, "lastUpdate": "2026-07-24"}`.

**`llms.txt`** (`text/plain`) — Markdown-ish guidance for LLM agents (name, summary, key URLs),
from `wellknown.llms.source` (a file reference or inline text).

**`agent-skills.json`** (`application/json`) — the agent-skills discovery document; emitted only
when `wellknown.agentSkills.skills` is non-empty, else absent (→ correctly not-applicable).

## 6. security.txt refresh (never silently expires)

`Expires` must be a future date (RFC 9116); a frozen file eventually lapses and the criterion
regresses. `wellknown:generate` recomputes `Expires = now + expiresMonths` and rewrites the file.

Run it **at deploy time**. netresearch.de deploys weekly (Wednesday Concourse run), so `Expires`
refreshes every deploy and stays ~6 months out — no separate cron needed. Sites that deploy rarely
add a TYPO3 Scheduler task or CI cron invoking the same command. The generated files are **not
committed to git** (they carry a moving date); they are produced into the docroot during the
build/deploy step.

## 7. General vs. netresearch.de-specific

| Piece | General (fleet) | netresearch.de-specific |
|---|---|---|
| Extension `nr_wellknown` | ✅ one repo, composer | require it |
| nginx `try_files` line | ✅ t3re, all versions | inherited via the runtime |
| Site-config schema | ✅ defined by the extension | filled in `config/sites/main/config.yaml` |
| Content values | defaults only | security contact, policy URL, change-password target, llms.txt body |

## 8. Testing & acceptance

**Extension functional tests:** the middleware returns 302 with the configured `Location` for
`change-password`; `wellknown:generate` writes a `security.txt` that parses and whose `Expires`
is in the future; `gpc.json` matches the expected shape; excluded resources are never written.

**Acceptance oracle — the Website-Spec-Checker.** After deploy, re-run the netresearch.de census
and confirm:
- the 5 in-scope criteria flip `not_met` → `met`,
- the 14 out-of-scope criteria stay correctly `not_applicable` (not fabricated),
- `well-known-uris.well-known-uris` no longer reports "blanket-blocked".

## 9. Rollout order

1. Build and release `netresearch/nr-wellknown` (internal Packagist / composer.netresearch.de).
2. Merge the one-line `try_files` change into `netresearch/t3re` and let it ship in the runtime
   image; deploy the runtime.
3. netresearch.de: `composer require netresearch/nr-wellknown`, fill `wellknown` in the site
   config, wire `wellknown:generate` into the deploy, deploy.
4. Verify via the census report.
5. Fleet: opt-in per site (require the extension + fill site config). The `try_files` line is
   already fleet-wide from step 2, inert until a site configures resources.

## 10. Deferred / open

- **PGP-signed security.txt** (RFC 9116 SHOULD). Adds key management and signature automation;
  out of this iteration, tracked for a follow-up.
- **`change-password` for sites without frontend accounts.** If netresearch.de has no `fe_users`
  password-change page, `change-password` is legitimately not-applicable there; leave
  `wellknown.changePassword` unset and it 404s correctly. Confirm the target during implementation.
- **GitLab namespace** for the extension repo (`netresearch/nr-wellknown` vs a coding-ai/TER path)
  — confirm before creating the remote and pushing.
