# Incident — MetaShare prerelease clone still remote-DELETEs prod (2026-08-09)

ClickUp: [CU-869edzz7b](https://app.clickup.com/t/869edzz7b) · New Relic account `2906135` · APM `real_product_manager_backend` · Window: **SINCE today** only (2026-08-09).

## Setup (locked with tester)

| Item                               | Value                                                                                        |
| ---------------------------------- | -------------------------------------------------------------------------------------------- |
| Prod host                          | `devowl-metashare.waysseab.hemsida.eu`                                                       |
| Clone host                         | `devowl-metashare-staging3.waysseab.hemsida.eu`                                              |
| Plugin build on **both**           | Prerelease **5.3.0-23575** (includes sever + **no** `update_option_siteurl`)                 |
| Programmatic filter                | Hostname contains `staging` → development key `ba37dd19-…`; else production key `fb80ce46-…` |
| Observed client UUID after failure | `3955393a-3f86-41d2-a91a-8f2b79edd4ca` (incident id — not a reusable fixture)                |

## Method note

Error-only access logs (`level=warn` / `422`) are **insufficient**. Successful `DELETE`/`POST`/`GET` (`200`/`201`) appear in **Transaction** (and User-Agent), not necessarily in the same Log facet used for `LicenseActivationNotFound`. Always correlate via `request.headers.userAgent` + `traceId` for clone incidents.

## Timeline (CEST, 2026-08-09)

| Time         | Caller (User-Agent site URL) | Call                                                | HTTP                                | TraceId (prefix) |
| ------------ | ---------------------------- | --------------------------------------------------- | ----------------------------------- | ---------------- |
| 12:30:47     | Prod `/sv/`                  | `POST /1.0.0/license/activation`                    | **201**                             | `0c08de77…`      |
| 12:49:43     | Prod `/en/`                  | `GET /1.0.0/license/activation`                     | **200**                             | `6fdb46c1…`      |
| **12:53:14** | **Staging3** (no path)       | **`DELETE /1.0.0/license/activation`**              | **200**                             | `1b99ed6b…`      |
| 12:53:14     | Staging3                     | `POST /1.0.0/license/activation`                    | **201**                             | `6266a233…`      |
| 12:53:49     | Prod `/en/`                  | `GET …?licenseKey=fb80ce46-…&clientUuid=3955393a-…` | **422** `LicenseActivationNotFound` | `bd57c3b9…`      |
| 12:57:15     | Staging3 `/sv/`              | `GET /1.0.0/license/activation`                     | **200**                             | `7c01f9f5…`      |

## Findings

### 1. Who reported `LicenseActivationNotFound`?

**Production** itself:

`User-Agent: WordPress/6.9.6; https://devowl-metashare.waysseab.hemsida.eu/en/`

That GET is the UI/sync discovery — not the kill.

### 2. What deactivated prod?

**Staging3** issued a successful remote **`DELETE` (~35s earlier)**, then immediately `POST` activate. Classic `activateProgrammatically` shape when local key/type ≠ programmatic filter:

1. Cloned DB still has prod key + prod client UUID
2. Filter returns staging key → mismatch → `deactivate(true)`
3. Remote DELETE with cloned identity
4. `activate()` with staging key → `201`

Prod was healthy until that DELETE (GET **200** at 12:49:43).

### 3. Sever did **not** intercept

If [`probablySeverClonedLicenseIdentity()`](https://git.owlinfra.de/devowlio/devowl-wp/-/blob/fc36c02dfc364f15645eafc186346d821d5f21b1/wordpress-packages/real-product-manager-wp-client/src/license/License.php#L296-L362) had returned `true`, [`LicenseActivation::deactivate($remote=true)`](https://git.owlinfra.de/devowlio/devowl-wp/-/blob/fc36c02dfc364f15645eafc186346d821d5f21b1/wordpress-packages/real-product-manager-wp-client/src/license/LicenseActivation.php#L168-L175) would skip the HTTP DELETE. The **200 DELETE from Staging3** proves sever returned **`false`** on that request.

### 4. Ruled out for this incident

| Hypothesis                                                    | Status                                                                    |
| ------------------------------------------------------------- | ------------------------------------------------------------------------- |
| Prerelease missing on prod or clone                           | **Ruled out** — tester confirmed 5.3.0-23575 on both                      |
| Silent `update_option_siteurl` hostname rewrite still present | **Ruled out** — confirmed absent in that prerelease on both instances     |
| “Prod was never activated today”                              | **Ruled out** — prod `POST 201` + later `GET 200` before the clone DELETE |
| Kill attributed to the 422 GET host                           | **Ruled out** — 422 is prod discovering a already-deleted activation      |

### 5. Remaining explanations (sever returned false)

Without the siteurl rewrite, sever still skips (or no-ops) when any of these hold — and **historically** `deactivate(true)` then remote-DELETEd. **Addressed:** any blocked host check with license identity now returns `WP_Error` `rpm_wpc_host_check_blocked` (callers must not remote; deactivate falls back to local-only).

1. **Empty persisted license hostname** — bootstrap branch writes current host into the option and returns `false` **without** clearing key/UUID. Next (or same) programmatic deactivate can still DELETE with the cloned UUID if hosts already “match” after bootstrap. **Still open.**
2. **`WP_CLI` (pre-fix)** — entire sever gate was skipped (`!$isWpCli`, CU-869482eaf). **Addressed:** WP-CLI skip removed.
3. **Empty `Utils::getCurrentHostname()`** / **redirect** / **IP** / **unparsable or `http:`/`https:`** / **temp-cloud regexp** / **`RPM_WP_CLIENT_SKIP_DYNAMIC_HOST_CHECK`** — **Addressed:** `WP_Error` `rpm_wpc_host_check_blocked` when identity present.
4. **Persisted hostname already equals current staging host** — mismatch never seen (prior bootstrap (1), HostMap filter, or other option write). **Still open.**

## Implications for the concept

- Removing `update_option_siteurl` is **necessary but not sufficient**. Sever must not be bypassable on the programmatic key-switch path after a DB clone.
- Programmatic sites with **different prod vs staging keys** are the highest-risk path: key mismatch forces `deactivate(true)` on every clone boot.
- Telemetry: prefer Transaction + User-Agent for clone forensics; do not conclude “no DELETE” from warn-level logs alone.

## Follow-up — Staging4 clone + WP Toolkit segfault (2026-08-09 evening)

| Item                | Value                                                                                                      |
| ------------------- | ---------------------------------------------------------------------------------------------------------- |
| Clone host          | `devowl-metashare-staging4.waysseab.hemsida.eu`                                                            |
| Toolkit error       | `Failed to reset cache` → WP-CLI `segmentation fault` (instance `#17642`)                                  |
| Workaround observed | Deactivate Real Cookie Banner on **prod** before clone → Toolkit clone succeeds                            |
| NR                  | No `staging4` MetaShare User-Agent on license routes; only MetaShare `DELETE` today remains Staging3 12:53 |

**Root cause (code):** After removing `update_option_siteurl` hostname sync + WP-CLI sever skip, Toolkit WP-CLI (`--url` = clone host) sees host mismatch → `probablySeverClonedLicenseIdentity()` → `deactivate(false)` → `probablySeverClonedLicenseIdentity()` again → **infinite recursion** → stack overflow reported as segfault. Pre-fix, siteurl sync made hosts “match” so sever never entered that branch (and instead allowed remote DELETE). Deactivating RCB skips loading the client → no recursion.

**Fix:** Reentrancy guard `$severingClonedLicenseIdentity` in `License::probablySeverClonedLicenseIdentity()` (return `true` on re-entry so remote stays off).

## Engineering follow-up (open)

- [x] Harden WP-CLI / empty-hostname / other host-check skip paths: remove `!$isWpCli`; blocked host check with identity → generic `WP_Error` `rpm_wpc_host_check_blocked`; callers skip remote accordingly.
- [x] Fix sever↔deactivate infinite recursion (Staging4 Toolkit segfault when RCB active).
- [x] Plan 04 workflow hardening: [plans/04-wp-client-sever-workflow-hardening.md](../plans/04-wp-client-sever-workflow-hardening.md) (`clearLocalLicenseIdentity`, reclaim choke point).
- [ ] Execute CI runner matrix: [references/04-ci-runner-clone-sever-test-plan.md](04-ci-runner-clone-sever-test-plan.md).
- [ ] Reproduce on Staging3/4: after Prod→Staging sync, before first admin hit — inspect `rpm-wpc-hostname_*`, `rpm-wpc-code_*`, `rpm-wpc-uuid_*`, and whether first license traffic is WP-CLI/cron vs web.
- [ ] Re-test with reclaim-enabled build: after sever, reclaim should restore staging hostname activation without needing DELETE of prod; Toolkit cache-reset must not segfault with RCB active.
- [ ] Consider forcing programmatic re-activate after local `LicenseActivationNotFound` deactivate when a programmatic filter exists (prod self-heal after a kill).
- [ ] Remaining risk if (1) or (4): persisted host already equals staging while UUID is still prod’s — sever never fires; remote DELETE still possible. Option dump after sync still needed.

## NRQL snippets (account `2906135`)

```sql
FROM Transaction
SELECT timestamp, request.method, http.statusCode, `request.headers.userAgent`, request.uri, traceId
WHERE appName = 'real_product_manager_backend'
  AND request.uri LIKE '%license%'
  AND (`request.headers.userAgent` LIKE '%metashare%' OR `request.headers.userAgent` LIKE '%waysseab%')
SINCE today
LIMIT 100
```

```sql
FROM Transaction
SELECT timestamp, request.method, http.statusCode, `request.headers.userAgent`, request.uri, traceId
WHERE traceId = 'bd57c3b9dd154c697d8f8d6f0ba30683'
SINCE today
```
