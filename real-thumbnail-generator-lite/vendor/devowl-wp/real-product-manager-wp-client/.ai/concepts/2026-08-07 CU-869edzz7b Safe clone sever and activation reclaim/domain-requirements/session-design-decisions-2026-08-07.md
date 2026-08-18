<!--
Source: design session 2026-08-07 (agent + Matthias), extending CU-869edzz7b after prerelease reproduction
Export date: 2026-08-07
Rule: do not annotate the ClickUp export inline; this file captures locked product/design decisions for framing synthesis + Phase 2.2
-->

# Session design decisions (2026-08-07)

Framing for conception beyond the original sever-only ticket. Counter-points and open trade-offs belong under `spec/` after Phase 2.2.

## Problem beyond the original sever fix

Prerelease `probablySeverClonedLicenseIdentity` did not fire in a WP Toolkit-style clone because `update_option_siteurl` silently rewrote the persisted license hostname when siteurl was updated (CLI / migration tools), erasing the clone signal. Origin of that hook: CU-g150eg (Settings → General rename), not staging clones.

## Locked product decisions

1. **First staging activation may stay manual** — user creates staging and activates once; that is acceptable.
2. **Repeated Prod→Staging sync** — if the hostname was already activated before, restore that decision without asking again when proof allows.
3. **Remove silent hostname rewrite** via `update_option_siteurl` (License.php) so clones keep the mismatch signal.
4. **No Freemius-style admin notice / user choice** in the first delivery — prefer automatic reclaim + existing programmatic filter; on any reclaim failure, WordPress ignores the error and continues as today (programmatic activate, else existing hint notice).
5. **Reclaim proof** — request always sends `licenseKey` + `clientUuid` + `hostname` (+ product as today). Real Product Manager finds an existing activation for the current hostname; Real Commerce checks that the proof license and the activation’s license belong to the **same subscription**. Client UUID must belong to an activation under that subscription (validated in RPM). Prod and staging keys may differ.
6. **RPM ↔ RC** — HTTP contract-first (`createContract`), **not** NATS / `createBrokerContract` (no broker between RPM and RC).
7. **Cache** — RC `licenseKey → subscriptionId` (or equivalent) in `@devowl-wp/kv-cache` hashmap with **per-field TTL**. Cache membership only, not the full reclaim decision. Short negative TTL for unknown keys; **invalidate** the field when a subsequent activate/reclaim succeeds for that key so the next reclaim re-queries RC.
8. **Kill switch** — reclaim route behind env feature flag default **true** (e.g. `REAL_PRODUCT_MANAGER_LICENSE_ACTIVATION_RECLAIM_ENABLED`). When disabled → HTTP **503** + stable error code (e.g. `LicenseActivationReclaimDisabled`). WP treats it like any reclaim error (ignore, continue).
9. **Local restore after successful reclaim** — persist the full local option set as on normal activate (`code`, `uuid`, `hostname`, `installationType`, `telemetry`, `noUsage`, clear `hint`, `licenseActivation` blob via `receivedRemoteLicenseActivation`), driven by the reclaim response body (same shape as POST activate), not by cloned prod telemetry/type.

## Explicitly out of first delivery (unless Phase 2.2 reopens)

- Freemius-like migrate / temporary-duplicate / long-term-duplicate notice UX
- Domain HTTP challenge / `.well-known` proof
- Hostname-only reclaim without license key + UUID proof
