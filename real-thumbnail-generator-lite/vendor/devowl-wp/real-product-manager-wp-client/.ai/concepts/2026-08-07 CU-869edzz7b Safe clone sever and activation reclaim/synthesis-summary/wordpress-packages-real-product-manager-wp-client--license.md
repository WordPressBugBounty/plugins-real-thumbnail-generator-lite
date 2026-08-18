# wordpress-packages/real-product-manager-wp-client — license identity

## Source paths

- `wordpress-packages/real-product-manager-wp-client/src/license/License.php`
- `wordpress-packages/real-product-manager-wp-client/src/license/LicenseActivation.php`
- `wordpress-packages/real-product-manager-wp-client/src/client/LicenseActivation.php`
- `wordpress-packages/real-product-manager-wp-client/src/client/TelemetryData.php`
- `wordpress-packages/real-product-manager-wp-client/src/license/TelemetryData.php`

## Responsibility

Owns per-plugin / per-blog license identity in WordPress options (`code`, client `uuid`, base64-encoded hostname, installation type, telemetry opt-in, hint, activation blob), host-mismatch severing, best-effort reclaim after sever, programmatic activate via filter, and HTTP calls to Real Product Manager for activate / deactivate / reclaim / sync / status / telemetry. Does **not** own quota / same-subscription reclaim on the server, Customer Center UI, or RPM entity persistence.

## Public surface

- `License` — domain object for one slug + blog; options, sever, sync, programmatic activate, remote validation.
- `License::probablySeverClonedLicenseIdentity()` — local-only drop of identity when current host ≠ persisted host via `clearLocalLicenseIdentity` (never `deactivate`); returns `true` (severed), `false` (unchanged / no identity), or `WP_Error` `rpm_wpc_host_check_blocked` when the host check cannot run while identity is present — callers must not call remote. On `true`, runs `afterSeverBestEffortRestore()` once per request.
- `License::afterSeverBestEffortRestore()` — one-shot: `probablyReclaimLicenseActivation` then `activateProgrammatically(true)`.
- `License::validateNewHostName()` — admin `shutdown` hook; delegates to `probablySeverClonedLicenseIdentity()` (restore already chained on sever).
- `License::activateProgrammatically($force)` — reads `DevOwl/RealProductManager/License/Programmatic/{slug}` and activates remotely; re-checks local code after deactivate (sever/reclaim may already match).
- `LicenseActivation::clearLocalLicenseIdentity($validateStatus, $help, $clearClientUuid)` — option wipe + StatusChanged; no HTTP; no sever.
- `LicenseActivation::activate` / `deactivate($remote)` — activate POST; deactivate runs sever first, skips remote+local wipe when sever already handled this request (`=== true`), else optional DELETE then `clearLocalLicenseIdentity`.
- `client\LicenseActivation` — HTTP POST/PATCH/GET/DELETE to `1.0.0/license/activation` + `postReclaim` → `…/reclaim`.
- `client\TelemetryData::put()` — HTTP PUT to `1.0.0/telemetry`; skips when sever returns `WP_Error`.

## Data flow

1. Construct `License`: if external updates enabled and `is_admin()`, `shutdown` → `validateNewHostName`. **No** `update_option_siteurl` hostname sync.
2. `initialize()` → telemetry transmit, deferred daily sync (`probablySyncWithRemote`), migration hint.
3. Before remote work, callers invoke `probablySeverClonedLicenseIdentity()` (sync, fetch status, activate, telemetry PUT, deactivate).
4. Sever on mismatch: stash `{code,uuid}` → `clearLocalLicenseIdentity(…, clearClientUuid=true)` → `afterSeverBestEffortRestore` (reclaim best-effort, then programmatic). Never remote DELETE with cloned UUID.
5. Activate / reclaim success: `persistLocalLicenseActivationFromRemote`.
6. Programmatic path may `deactivate(true)` then `activate` when force/key/type changed; after sever/reclaim, skips activate if local already matches filter.

## Key types / contracts

- Option prefixes — `RPM_WP_CLIENT_OPT_PREFIX` + `-code_|-uuid_|-hostname_|-telemetry_|-installationType_|-hint_|-licenseActivation_|-noUsage_` + slug.
- `VALIDATE_NEW_HOSTNAME_SKIP` / `VALIDATE_NEW_HOSTNAME_SKIP_BY_REGEXP` — skip sever for broken URLs, AWS/Azure/ELB/temp/hostinger hosts.
- `DevOwl/RealProductManager/License/Programmatic/{slug}` — filter returning false or activation array (`key`, optional `environment`, `telemetry`, `optInPrivacyPolicy`).
- Remote error codes handled in `validateRemoteResponse` — e.g. `LicenseActivationNotFound`, `ClientNotFound`, expired/revoked → local `deactivate(false)`.
- HTTP endpoint constants — `client\LicenseActivation::ENDPOINT_LICENSE_ACTIVATION`, `ENDPOINT_LICENSE_ACTIVATION_RECLAIM`, `client\TelemetryData::ENDPOINT_TELEMETRY`.

## Non-obvious constraints

- Persisted hostname is **base64-encoded** so DB search-replace migrators do not rewrite it; decode only via `getKnownHostname()`.
- Sever skip guards (empty host, redirect, IP, unparsable/`http:`/`https:`, temp AWS/Azure/ELB/hostinger hosts, `RPM_WP_CLIENT_SKIP_DYNAMIC_HOST_CHECK`): with license identity → `WP_Error` so remote is blocked; without identity → `false`.
- Callers: `true` → already severed this request (deactivate returns without DELETE/wipe so reclaim can stick); `WP_Error` → no remote; `false` → remote OK when hosts match or no identity.
- Admin `shutdown` is **not** the only gate: every remote path must call sever; CLI/web sever both run reclaim via `afterSeverBestEffortRestore`.
- Intentional `deactivate` does **not** clear UUID (`clearClientUuid=false`); sever clears UUID. `licenseActivation` blob retained (feature flags).
- Multisite: every option read/write goes through `switch()` / `restore()` for the license blog id.
- Client properties always send current `hostname` (via `Utils::getCurrentHostname()`), not the persisted option, on POST/PATCH.

## Known pitfalls

- Empty persisted hostname with existing code bootstraps current host into the option (back-compat) instead of severing — residual clone risk if hostname was pre-written to staging.
- Programmatic activate must keep sever-before-remote on `deactivate(true)`; do not bypass `probablySeverClonedLicenseIdentity` on new remote entry points.
- Filter result maps `key` → internal `code` and `environment` → `type`; invalid telemetry/privacy opt-in returns false (no activate).

## Pointers

- Sibling: `./backends-real-product-manager--license-activation.md`
- Sibling: `./api-real-product-manager--license-activation.md`
- Framing: `../domain-requirements/cu-869edzz7b.md`, `../domain-requirements/session-design-decisions-2026-08-07.md`
- Hardening plan: `../plans/04-wp-client-sever-workflow-hardening.md`
