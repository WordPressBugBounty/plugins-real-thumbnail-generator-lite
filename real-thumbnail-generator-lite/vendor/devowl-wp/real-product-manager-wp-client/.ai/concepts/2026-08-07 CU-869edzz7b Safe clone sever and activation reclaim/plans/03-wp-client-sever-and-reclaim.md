---
status: done
sub-project: wp-client-sever-and-reclaim
index: "03"
depends-on: [rpm-activation-reclaim]
---

# 03 — WP client sever + reclaim

## Goal

Stop silent hostname rewrite on `update_option_siteurl`. On host mismatch: request-local stash of license key + client UUID, local sever (no remote DELETE with cloned identity), best-effort reclaim against RPM, full local option restore on success; ignore all reclaim errors and keep existing programmatic / hint flow.

Honours: [spec/interfaces.md](../spec/interfaces.md) § wp-client · [spec/safe-clone-sever-and-activation-reclaim.md](../spec/safe-clone-sever-and-activation-reclaim.md).

## Depends on

- **02** reclaim route live on RPM (or WP reclaim call fails open — sever still ships).

## Out of scope

- New admin notices / Freemius UX.
- Calling Real Commerce from PHP.
- Changing programmatic filter contract.

## Tasks

### T1 — Remove `update_option_siteurl` hostname sync

- `wordpress-packages/real-product-manager-wp-client/src/license/License.php`
    - Remove `add_action('update_option_siteurl', …)` and method `update_option_siteurl()` (or leave empty deprecated stub only if external code hooks it — prefer **full remove**)
- Update CHANGELOG under Unreleased

**Verification:** `dowl lint:phpcs` with `--standard=./vendor/devowl-wp/phpcs-config/scripts/phpcs.xml` on touched PHP; grep shows no remaining `update_option_siteurl` registration in this package.

### T2 — Request-local stash on sever

- Extend `probablySeverClonedLicenseIdentity()` (or adjacent private state) to capture `{ code, uuid }` **before** local deactivate / UUID clear
- Expose read-once getters for the current request (do **not** persist stash to options)
- Ensure remote paths still call sever first; remote DELETE still skipped when severed

**Verification:** PHPUnit/Vitest if package has coverage for License; otherwise a focused test file if fixtures exist; phpcs.

### T3 — Shared persist helper from remote activation

- Extract from `LicenseActivation::activate` success path a method e.g. `persistLocalLicenseActivationFromRemote(array $licenseActivation, …)` writing:
    - code, uuid, hostname (base64 current), installationType, telemetry, noUsage=0, delete hint, dismiss notice, `receivedRemoteLicenseActivation`, StatusChanged action
- `activate()` calls the helper; reclaim success will too

**Verification:** phpcs; no behaviour change on manual activate (smoke or existing tests).

### T4 — HTTP `postReclaim` + orchestration

- `src/client/LicenseActivation.php` — `postReclaim($licenseKey, $clientUuid, $hostname)` → `1.0.0/license/activation/reclaim` with product/variant from initiator
- After sever in `validateNewHostName()` (and any other post-sever reclaim entry):
    1. `activateProgrammatically(true)` first if desired order stays: **spec:** programmatic then reclaim? Session said: sever → reclaim best-effort → programmatic as today. Lock order: **after sever, try reclaim if stash present; then `activateProgrammatically(true)` as today** (so MetaShare programmatic still wins when reclaim fails / wrong key). If reclaim already restored license, programmatic no-ops / matches.
    2. Reclaim: any `WP_Error` / non-success → ignore
    3. On success array → persist helper
- Do **not** add new hint messages for reclaim failure

**Verification:** phpcs; manual: clone-like hostname option mismatch → no remote DELETE; with RPM reclaim + same subscription → options restored without paste.

### T5 — Package version / consumer note

- Bump / changelog for `real-product-manager-wp-client` so plugins pick up fix
- No plugin code change required unless pinned version

**Verification:** CHANGELOG entry references CU-869edzz7b.
