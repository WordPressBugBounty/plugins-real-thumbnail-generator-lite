# Safe clone sever and activation reclaim

## Goal

1. **Never** let a WordPress DB clone (Prod → Staging) talk to Real Product Manager with production’s client UUID in a way that deactivates or rebinds production’s remote activation.
2. On **repeated** Prod → Staging syncs, when the staging hostname was already activated once under the same customer subscription, **restore** that local license state automatically (best-effort), including when production and staging use different license keys.
3. Keep first-time staging activation as a normal manual (or programmatic) activate — no Freemius-style choice UI in this delivery.

## Candidate approaches

### A — Harden sever / hostname sync only

Sharpen or remove `update_option_siteurl` hostname rewrite; keep `probablySeverClonedLicenseIdentity` before remote calls. No reclaim.

- **+** Smallest change; production safe after clone.
- **−** Every Prod → Staging resync forces manual or programmatic re-activation; MetaShare-style dual keys need the programmatic filter forever.

### B — Freemius-like admin notice (migrate / temporary / long-term)

Detect host mismatch, Safe Mode (no damaging remote sync), user picks outcome.

- **+** Explicit user intent for rename vs clone.
- **−** Opposes “as automatic as possible”; agency staging workflows still click; does not by itself restore after resync without further remote reclaim.

### C — Sever + subscription-scoped reclaim (chosen)

Remove silent `siteurl` hostname rewrite; sever locally on mismatch (no remote DELETE with cloned identity); after sever, best-effort `POST /license/activation/reclaim` with proof `(licenseKey, clientUuid, hostname)`; RPM finds activation for hostname, RC answers `sameSubscription(keyA, keyB)`, RPM also proves the proof UUID’s activation license is under that same subscription; restore full local options from reclaim response. Kill-switch feature flag (default on).

- **+** Matches locked session decisions; dual keys on one subscription; resync without re-paste when proof holds; fail-open on WP.
- **−** Cross-service HTTP + cache; proof weaker than domain challenge (stolen key+UUID from clone DB); new contract surface.

## Chosen direction with rationale

**Approach C**, refined by Phase 2.2 answers:

| Topic                      | Decision                                                                                                                                                                                                                                                      |
| -------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| WP `update_option_siteurl` | **Remove** the silent persisted-hostname rewrite (CU-g150eg convenience yields to clone safety). Domain rename without programmatic filter falls back to existing sever + manual/programmatic activate.                                                       |
| Sever                      | Keep / rely on `probablySeverClonedLicenseIdentity`: local deactivate only; clear UUID from options; **never** remote DELETE/PATCH/GET with cloned identity.                                                                                                  |
| Reclaim API                | **New** contract-first `POST /license/activation/reclaim` on Real Product Manager (not an extension of legacy POST activate).                                                                                                                                 |
| Proof body                 | Always `licenseKey` + `clientUuid` + `hostname` (+ product identifiers as required by RPM).                                                                                                                                                                   |
| RC check                   | Contract-first HTTP on Real Commerce: `sameSubscription(licenseKeyA, licenseKeyB): boolean` (no NATS).                                                                                                                                                        |
| RPM UUID check             | In addition to RC: prove the proof `clientUuid` belongs to an activation whose license is in the **same subscription** as the hostname-target activation (via license-key ↔ subscription relationship / RC as needed).                                       |
| Stash (request-local)      | Before clearing options on sever, hold `licenseKey` + `clientUuid` in memory for this request only; call reclaim with that stash; do not persist the stash across requests.                                                                                   |
| Failure                    | Reclaim is **best-effort only**. Any error (network, 503 kill-switch, 4xx, proof fail) → **ignore**; no new notice logic; continue existing post-sever path (`activateProgrammatically` then existing hints / manual).                                        |
| Success restore            | Persist the same local option set as normal activate, driven by reclaim response (`code`, `uuid`, `hostname`, `installationType`, `telemetry`, `noUsage`, clear `hint`, `licenseActivation` blob).                                                            |
| RC membership cache        | `@devowl-wp/kv-cache` map with per-field TTL for data that backs `sameSubscription` / license→subscription lookups; short negative TTL; **invalidate** relevant fields when activate or reclaim succeeds for a key. Do not cache the reclaim decision itself. |
| Kill switch                | Env flag default **true** (e.g. `REAL_PRODUCT_MANAGER_LICENSE_ACTIVATION_RECLAIM_ENABLED`); when false → **503** + stable code `LicenseActivationReclaimDisabled`. WP treats as any reclaim failure.                                                          |

**Rationale:** Production must never be killed by staging remote calls (ticket + NR reproduction). Agencies already accept one staging activate; the pain is **resync wiping local options** while remote still holds the staging activation. Subscription-scoped proof allows different keys without hostname-only insecurity. Fail-open avoids a worse UX than today when reclaim cannot run.

## Open questions

_None blocking Phase 3._ Implementation detail (exact RC path name, cache key layout, whether RPM calls RC once or twice for UUID’s license key) deferred to Phase 3.3 / plans.

## Not in scope

- Freemius-style migrate / temporary-duplicate / long-term-duplicate admin notice
- Domain HTTP / `.well-known` ownership challenge
- Hostname-only reclaim (no license key + UUID)
- NATS / `createBrokerContract` between RPM and RC
- Changing successful normal activate/deactivate semantics beyond reclaim + removing `update_option_siteurl` rewrite
- Migrating legacy `routeLocation*` activation CRUD to contract-first (reclaim only is new-contract)
- Auto-migrating intentional Settings → General domain rename without user/programmatic re-activate
