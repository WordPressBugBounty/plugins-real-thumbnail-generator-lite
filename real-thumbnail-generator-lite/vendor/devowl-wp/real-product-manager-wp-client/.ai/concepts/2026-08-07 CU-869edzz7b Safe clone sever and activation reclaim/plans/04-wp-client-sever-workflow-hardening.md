---
status: done
sub-project: wp-client-sever-workflow-hardening
index: "04"
depends-on: [wp-client-sever-and-reclaim]
---

# 04 — WP client sever workflow hardening

## Goal

Keep Approach C (sever + reclaim + no `siteurl` hostname sync). Refactor the **client orchestration** so clone safety does not depend on reentrancy tricks, admin-only reclaim, or every caller remembering the right `true`/`false`/`WP_Error` dance.

Honours: [spec/safe-clone-sever-and-activation-reclaim.md](../spec/safe-clone-sever-and-activation-reclaim.md) · incidents [03](../references/03-metashare-prerelease-clone-incident-2026-08-09.md) · test plan [04](../references/04-ci-runner-clone-sever-test-plan.md).

## Why

| Symptom                            | Cause in `b8421dac` shape                                                                  |
| ---------------------------------- | ------------------------------------------------------------------------------------------ |
| Toolkit WP-CLI segfault (Staging4) | `probablySever` → `deactivate` → `probablySever` infinite recursion                        |
| Staging3 remote DELETE             | Sever returned false (or skipped); programmatic `deactivate(true)` used cloned UUID        |
| Reclaim easy to miss               | Only wired from admin `shutdown` `validateNewHostName`; CLI can sever and drop stash first |

Backend reclaim + RC `sameSubscription` stay as shipped. This plan is **WP client only**.

## Target workflow

```
ensureLocalIdentitySafeForRemote():
  if hostCheckBlocked && hasIdentity → refuse remote (WP_Error)
  if host mismatch && hasIdentity → severLocalOnly()  // no HTTP
       → stash {code,uuid} (request-local)
       → probablyReclaim() best-effort   // same request, any SAPI
       → return "severed" (callers: no remote with old UUID)
  else → ok

deactivate($remote):
  ensure…()  // may sever + reclaim
  if remote && still have identity for THIS host → DELETE
  clearLocalIdentity()  // never calls ensure/sever

activateProgrammatically:
  on key/type mismatch → deactivate(true) only after ensure…
  // after clone, ensure already cleared cloned UUID → DELETE skipped / local-only
```

## Layering (sketch)

### 1. `clearLocalLicenseIdentity($validateStatus = null, $help = '')` (private / package)

Moves the option clears + hint + `StatusChanged(false)` out of the middle of sever.

- **Does not** call `probablySeverClonedLicenseIdentity`.
- **Does not** HTTP.
- Used by: sever path, `deactivate` after optional remote DELETE, remote-error revoke paths.

### 2. `probablySeverClonedLicenseIdentity()` slimmed

- Host-check blocked + identity → `WP_Error` `rpm_wpc_host_check_blocked` (unchanged).
- Empty persisted host + code → bootstrap write current host (unchanged; known residual risk — see open).
- Host mismatch → stash → **`clearLocalLicenseIdentity('warning', …)`** + clear UUID → `true`.
- **Never** call `LicenseActivation::deactivate`.
- Keep a cheap reentrancy / “already severing” flag only as belt-and-braces (should be unused once deactivate no longer re-enters).

### 3. `deactivate($remote)`

```
switch
result = probablySever…()
if remote && result === true → remote = false   // already local-cleared
if remote && is_wp_error(result) → remote = false
if remote → client->delete(code, uuid)  // only when still have identity
clearLocalLicenseIdentity(...)          // idempotent if sever already cleared
restore
```

UUID clear: sever already clears; `clearLocal…` may leave UUID handling consistent (today sever clears UUID after deactivate — fold into clear helper so one place owns “drop identity”).

### 4. Reclaim after **every** successful sever in-request

- Extract `afterSeverBestEffortRestore()`: `probablyReclaimLicenseActivation()` then existing `activateProgrammatically(true)` when that is the product order.
- Call from:
    - `validateNewHostName` (admin shutdown) — thin wrapper
    - **and** any path that just got `probablySever…() === true` and should restore (at minimum: end of `ensure` used by sync/fetch/activate/telemetry **or** explicitly after sever inside those callers)
- Preferred single choke point: `ensureLocalIdentitySafeForRemote()` returns enum-like result; on `severed`, it runs reclaim+programmatic once per request (flag `$postSeverRestoreDone`).

### 5. Optional rename for clarity (same behaviour)

| Today                                | Proposed                                                                                     |
| ------------------------------------ | -------------------------------------------------------------------------------------------- |
| `probablySeverClonedLicenseIdentity` | keep name (public-ish) or alias `ensureLocalIdentitySafeForRemote`                           |
| return `true\|false\|WP_Error`       | keep for BC inside package; document strictly; avoid new public types unless tests need them |

### 6. Out of scope for 04

- Install fingerprint / clone token (residual risks (1)/(4) in incident).
- Freemius notice UX.
- Backend contract changes.
- Migrating legacy activation CRUD to contract-first.

## Tasks

### T1 — Reentrancy guard (ship blocker)

Already sketched in tree: `$severingClonedLicenseIdentity` early `return true`. Keep until T2 removes the cycle.

**Verify:** PHPUnit or manual WP-CLI `wp cache flush` on clone-like options does not segfault.

### T2 — `clearLocalLicenseIdentity` + sever stops calling `deactivate`

- Implement helper on `LicenseActivation` or `License`.
- Sever mismatch branch uses helper + UUID clear + stash only.
- `deactivate` uses helper for local wipe after optional remote.

**Verify:** phpcs; no `deactivate` call from inside `probablySever*`; Staging4-style CLI flush green.

### T3 — Post-sever restore choke point

- One-shot per request: reclaim then `activateProgrammatically(true)`.
- Wire so CLI/web sever both attempt reclaim when stash present (HTTP allowed).
- Admin `validateNewHostName` becomes: sever/ensure + restore choke (or no-op if already done).

**Verify:** test plan V3–V5 in [04 test plan](../references/04-ci-runner-clone-sever-test-plan.md).

### T4 — Programmatic path audit

- Confirm `activateProgrammatically` key mismatch cannot DELETE with a **cloned** UUID (ensure runs inside `deactivate(true)` / activate).
- After T2/T3, dual-key MetaShare path: sever → reclaim (staging key) **or** programmatic activate staging key; **no** prod DELETE in NR.

**Verify:** test plan V4 + NRQL checklist.

### T5 — Docs / CHANGELOG

- CHANGELOG under Unreleased for client package.
- Update synthesis-summary license bullet if API surface of helpers changes.
- Tick incident follow-ups that this plan closes.

## Success criteria

1. No recursion: sever never enters `deactivate`.
2. Clone / host-mismatch WP-CLI completes without segfault with RCB active.
3. Host-mismatch never produces RPM `DELETE` with production client UUID (NR).
4. Resync with prior staging activation + same subscription restores via reclaim without paste (when RPM reclaim up).
5. Intentional same-host deactivate still remote-DELETEs.
6. Test plan [04](../references/04-ci-runner-clone-sever-test-plan.md) all variants pass on `wordpress.ci-runner-12.owlsrv.de`.
