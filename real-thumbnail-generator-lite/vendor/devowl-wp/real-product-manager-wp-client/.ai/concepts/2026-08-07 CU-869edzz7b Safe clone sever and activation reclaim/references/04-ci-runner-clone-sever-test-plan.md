# Test plan — Clone sever / reclaim on CI WordPress runner

Target host: `https://wordpress.ci-runner-12.owlsrv.de`  
Concept: CU-869edzz7b · Implements against [plans/04-wp-client-sever-workflow-hardening.md](../plans/04-wp-client-sever-workflow-hardening.md) (and current 03 build until 04 lands).  
Automation: Playwright MCP (`dev-stack-browser` skill) + WP-CLI on the runner + New Relic Transaction checks (account `2906135`, app `real_product_manager_backend`).

## Credentials (execution-time only — do not commit)

| Surface            | How to obtain at run time                                                                                                                                                                           |
| ------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Traefik Basic Auth | Session operator or Infisical / `dowl workspace/exposedotenv` (`TRAEFIK_BASIC_AUTH_*`). Embed `https://<user>:<pass>@wordpress.ci-runner-12.owlsrv.de/…` on the **first** Playwright navigate only. |
| WordPress admin    | Username `wordpress`. Password supplied by the session operator at execution time (never write it into this file or other concept docs).                                                            |

If either secret is missing mid-run: stop and ask — do not guess.

## Preconditions

- [ ] Build under test deployed on the runner WP (RCB + embedded `real-product-manager-wp-client` with T1 reentrancy guard at minimum; prefer full plan 04).
- [ ] RPM reclaim route reachable from the runner (flag default on).
- [ ] Two license keys under the **same** RC subscription (call them `KEY_PROD` / `KEY_STAGING` in notes — store values only in session scratch, not here).
- [ ] Product/variant IDs match the plugin on the runner.
- [ ] NRQL access for User-Agent containing `ci-runner-12` / the site URL.
- [ ] WP-CLI available on the instance (Plesk/SSH or runner-documented path).

## Fixture recreation (no stack-local UUIDs in git)

Parameters to recreate, not concrete UUIDs:

1. Activate plugin on runner with `KEY_PROD` while siteurl host = runner host → record `clientUuid` as `UUID_A` (session only).
2. For reclaim variants: ensure RPM already has an **Activated** activation for hostname = runner host under `KEY_STAGING` (or activate once on a “staging” alias if the runner uses a second vhost — see V5).
3. After each destructive variant: restore known-good activation before the next case (or reset options + re-activate).

## Observation channels

| Channel                                                | Use                                                                               |
| ------------------------------------------------------ | --------------------------------------------------------------------------------- |
| Playwright                                             | Admin license UI, login, notices                                                  |
| `wp option get` / `wp option list --search='rpm-wpc-'` | `code`, `uuid`, `hostname` (base64), hint                                         |
| `wp cache flush` / Toolkit-equivalent CLI              | Segfault / exit code                                                              |
| NR Transaction                                         | `DELETE`/`POST`/`GET`/`reclaim` + `request.headers.userAgent` + `http.statusCode` |

NRQL template:

```sql
FROM Transaction
SELECT timestamp, request.method, http.statusCode, `request.headers.userAgent`, request.uri, traceId
WHERE appName = 'real_product_manager_backend'
  AND request.uri LIKE '%license%'
  AND `request.headers.userAgent` LIKE '%ci-runner-12%'
SINCE 2 hours ago
LIMIT 100
```

## Variants

Mark each: `[ ]` open · `[x]` pass · `[F]` fail · `[~]` N/A with reason.

### V0 — Smoke login (Playwright)

- [ ] First navigate with Basic Auth embedded → WP login screen or admin.
- [ ] Log in as `wordpress` → `wp-admin` loads.
- [ ] Plugin license screen reachable (RCB / updates license UI).

### V1 — Happy path same host (no clone)

Setup: activated with `KEY_PROD`, persisted hostname = current host.

- [ ] Admin load / license sync → NR `GET` or `PATCH` **200**, **no** unexpected `DELETE`.
- [ ] Options: code + uuid + hostname present and stable across refresh.

### V2 — Intentional remote deactivate (same host)

- [ ] Deactivate license in UI (or WP-CLI deactivate path if exposed) → NR `DELETE` **200** with this site’s User-Agent.
- [ ] Local code cleared; re-activate with `KEY_PROD` for following tests.

### V3 — Simulated clone: host mismatch, no reclaim target

Setup: while activated (`KEY_PROD`, `UUID_A`), set persisted hostname option to a **different** host (base64), leave code+uuid as prod clone. Do **not** pre-create staging activation for current host (or use wrong subscription key).

Trigger (run both):

1. Playwright: open `wp-admin` (admin `shutdown` / validate path).
2. WP-CLI: `wp cache flush` (and/or `wp option get siteurl`) with RCB **active**.

Expect:

- [ ] CLI exits **0** (no segfault / no core dump).
- [ ] Local identity cleared or replaced **without** NR `DELETE` for `UUID_A` / prod activation.
- [ ] Reclaim fails open → programmatic if configured, else warning hint / inactive UI.
- [ ] Prod activation for original prod host (if tested against shared RPM) still `GET` **200** when queried from a non-clone identity — at minimum: no DELETE from this User-Agent for the cloned uuid.

### V4 — Programmatic dual-key after mismatch (MetaShare shape)

Setup: filter returns `KEY_STAGING` when host matches runner (or always on this instance); DB still has `KEY_PROD` + `UUID_A` + persisted hostname = foreign host.

- [ ] First `init` / admin hit: NR shows **no** `DELETE` of `UUID_A`.
- [ ] Eventual local activation uses `KEY_STAGING` (`POST` **201** or reclaim **200**), new uuid ≠ `UUID_A` unless reclaim restored a prior staging uuid intentionally.
- [ ] CLI cache flush under same option state still exit 0.

### V5 — Reclaim success (resync)

Setup:

1. Activate once on runner with `KEY_STAGING` → note staging uuid `UUID_S`.
2. Simulate Prod→Staging sync: write options as `KEY_PROD` + `UUID_A` + persisted hostname = **prod-looking** host (not current).
3. Hit admin (or path that runs sever+reclaim).

Expect:

- [ ] NR `POST …/license/activation/reclaim` **200** (or equivalent) with runner User-Agent.
- [ ] **No** `DELETE` for `UUID_A`.
- [ ] Local options restored to staging activation (`KEY_STAGING`, `UUID_S` or reclaim response uuid), hostname = current host (base64).
- [ ] License UI fulfilled without pasting a key; info hint allowed.

### V6 — Host check blocked + identity present

Setup: force empty/unusable current hostname if feasible (or `RPM_WP_CLIENT_SKIP_DYNAMIC_HOST_CHECK` only in a disposable request — prefer documenting if runner cannot simulate).

- [ ] Any sync/fetch/activate attempt → no remote call with cloned uuid (NR quiet or non-DELETE error path).
- [ ] Returns / surfaces `rpm_wpc_host_check_blocked` where API exposes it; local identity retained until a safe sever can run.

If runner cannot simulate: mark `[~]` and note reason; cover in unit test instead.

### V7 — `siteurl` update must not rewrite license hostname

Setup: activated; note base64 `rpm-wpc-hostname_*`.

- [ ] `wp option update siteurl 'https://wordpress.ci-runner-12.owlsrv.de'` (or harmless equivalent) / Settings → General save without real domain change.
- [ ] License hostname option **unchanged** (no silent sync).
- [ ] If siteurl host **does** change to another host: next ensure severs locally (V3), still no DELETE with old uuid.

### V8 — Regression: RCB active during “Toolkit-like” CLI burst

Run sequentially with RCB active and forced host mismatch options:

```text
wp cache flush
wp rewrite flush
wp option get siteurl
```

- [ ] All exit 0.
- [ ] No NR `DELETE` from this host for cloned uuid during the burst.

### V9 — Telemetry / deferred sync after sever

- [ ] After V3/V5, deferred license sync does not resurrect cloned uuid or DELETE prod.
- [ ] Telemetry PUT skipped or runs only with safe identity (`TelemetryData` respects ensure/`WP_Error`).

## Pass / fail gate

**Pass:** V0–V2, V3, V4, V5, V7, V8 green (V6 optional if environment-limited).  
**Fail:** any segfault; any NR `DELETE` attributable to clone/mismatch User-Agent targeting prod/cloned uuid; reclaim success path that still requires manual key when proof should hold.

## Execution notes for the agent (later session)

1. Load `dev-stack-browser` skill; use Playwright MCP only for browser steps.
2. Ask operator for Basic Auth + WP password at start; do not echo passwords into concept files or commit messages.
3. Prefer WP-CLI for option surgery; Playwright for UI confirmation.
4. After the run, append a short results file `references/04-ci-runner-clone-sever-test-results-<n>.md` with checkbox outcomes + NR traceId prefixes — still **no** secrets, no full license keys (truncate to 8 chars if needed).
