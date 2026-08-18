# Safe clone sever and activation reclaim

Status: **ready for implementation** · ClickUp: [CU-869edzz7b](https://app.clickup.com/t/869edzz7b) · Scope: `wordpress-packages/real-product-manager-wp-client` · Created: 2026-08-07 · Conception: done (5.3)

## Summary

Protect production when a WordPress DB is cloned to staging (local sever, never remote DELETE with a cloned client UUID; remove silent `update_option_siteurl` hostname rewrite). On repeated Prod→Staging syncs, best-effort reclaim of an existing hostname activation via contract-first `POST /license/activation/reclaim`, proved by `licenseKey` + `clientUuid` + `hostname` and Real Commerce `sameSubscription`, with RPM also tying the proof UUID to the same subscription. Failures are ignored; kill-switch flag defaults on (503 when off).

Spec: [spec/safe-clone-sever-and-activation-reclaim.md](spec/safe-clone-sever-and-activation-reclaim.md) · [boundaries](spec/boundaries.md) · [interfaces](spec/interfaces.md)

## Summaries

- [`synthesis-summary/wordpress-packages-real-product-manager-wp-client--license.md`](synthesis-summary/wordpress-packages-real-product-manager-wp-client--license.md)
- [`synthesis-summary/backends-real-product-manager--license-activation.md`](synthesis-summary/backends-real-product-manager--license-activation.md)
- [`synthesis-summary/backends-real-commerce--license-subscription.md`](synthesis-summary/backends-real-commerce--license-subscription.md)
- [`synthesis-summary/api-real-product-manager--license-activation.md`](synthesis-summary/api-real-product-manager--license-activation.md)
- [`synthesis-summary/api-real-commerce--contract-first-http.md`](synthesis-summary/api-real-commerce--contract-first-http.md)
- [`synthesis-summary/backend-packages-kv-cache--map-field-ttl.md`](synthesis-summary/backend-packages-kv-cache--map-field-ttl.md)

## Sub-projects

- **01 — rc-same-subscription** — Contract-first HTTP on Real Commerce: `sameSubscription(licenseKeyA, licenseKeyB): boolean` (+ types in `api-real-commerce`), service-to-service auth as needed. Plan: [plans/01-rc-same-subscription.md](plans/01-rc-same-subscription.md)
- **02 — rpm-activation-reclaim** — Contract-first `POST /license/activation/reclaim` on Real Product Manager: hostname activation lookup, RC same-subscription + UUID↔subscription proof, kv-cache field-TTL for RC lookups, feature-flag kill switch (default on → 503 when off), invalidate cache on successful activate/reclaim. Plan: [plans/02-rpm-activation-reclaim.md](plans/02-rpm-activation-reclaim.md)
- **03 — wp-client-sever-and-reclaim** — Remove `update_option_siteurl` hostname rewrite; request-local stash of key+UUID on sever; best-effort reclaim HTTP + full local option restore; ignore all reclaim errors and keep existing post-sever flow. Plan: [plans/03-wp-client-sever-and-reclaim.md](plans/03-wp-client-sever-and-reclaim.md)
- **04 — wp-client-sever-workflow-hardening** — Untangle sever↔deactivate recursion; `clearLocalLicenseIdentity`; post-sever reclaim choke point for CLI+web. Plan: [plans/04-wp-client-sever-workflow-hardening.md](plans/04-wp-client-sever-workflow-hardening.md)

Glue: [plans/00-glue.md](plans/00-glue.md)

## References

- [01 — RC same-subscription smoke](references/01-rc-same-subscription-smoke.md)
- [02 — RPM activation reclaim smoke](references/02-rpm-activation-reclaim-smoke.md)
- [03 — MetaShare prerelease clone incident (2026-08-09)](references/03-metashare-prerelease-clone-incident-2026-08-09.md) — Prod still remote-DELETEd from Staging3 despite sever prerelease without `update_option_siteurl`; Transaction timeline + remaining sever skip paths
- [04 — CI runner clone sever test plan](references/04-ci-runner-clone-sever-test-plan.md) — Variants V0–V9 on `wordpress.ci-runner-12.owlsrv.de` (Playwright + WP-CLI + NR); secrets at execution time only
- [04 results #1](references/04-ci-runner-clone-sever-test-results-1.md) — 2026-08-09 run (local license API; reclaim route not deployed)
- [04 results #2](references/04-ci-runner-clone-sever-test-results-2.md) — after RPM restart; V5 reclaim PASS + customer-path matrix

## Index of ADRs

| ID                                                                         | Title                                                 | Status   |
| -------------------------------------------------------------------------- | ----------------------------------------------------- | -------- |
| [0001](adrs/0001-remove-silent-license-hostname-sync-on-siteurl-update.md) | Remove silent license hostname sync on siteurl update | accepted |
| [0002](adrs/0002-subscription-scoped-activation-reclaim-proof.md)          | Subscription-scoped activation reclaim proof          | accepted |
| [0003](adrs/0003-rc-same-subscription-internal-rpm-only.md)                | RC same-subscription route is internal and RPM-only   | accepted |

## Decision paths (domain-concept reflection)

- **Hostname sync on `siteurl` update** → Settings-only sharpen rejected (still hides some clones) → Freemius notice deferred → **remove silent `update_option_siteurl` rewrite** ([0001](adrs/0001-remove-silent-license-hostname-sync-on-siteurl-update.md)).
- **Restore after Prod→Staging resync** → hostname-only reclaim rejected (spoofable) → domain challenge deferred → **reclaim with licenseKey+clientUuid+hostname + RC `sameSubscription` + UUID under same subscription**; WP fail-open ([0002](adrs/0002-subscription-scoped-activation-reclaim-proof.md)).
- **RC subscription check exposure** → public/JWT rejected → embed subscription in RPM rejected → dedicated RPM inbound secret rejected (ops overhead) → **`profiles: ["internal"]` + existing SuperAdmin guard** ([0003](adrs/0003-rc-same-subscription-internal-rpm-only.md)).
- **First staging activate** → may stay manual (product lock); reclaim covers repeated syncs only.
- **Kill switch** → reclaim env flag default on; disabled → 503; WP ignores like any reclaim error.
