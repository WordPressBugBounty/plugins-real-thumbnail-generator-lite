---
status: accepted
created: 2026-08-07
supersedes:
superseded-by:
tags: [backend, real-product-manager, security]
---

# Subscription-scoped activation reclaim proof

## Context and Problem Statement

After a first staging activation, a later Prod→Staging DB sync wipes local license options while RPM still holds the staging activation. How do we restore that decision without requiring another key paste, including when production and staging use different license keys?

## Decision Drivers

- First staging activate may stay manual; resync must be automatic when proof allows.
- Proof must not be hostname-only (spoofable).
- Dual keys on one customer subscription (agency staging keys) must work.
- Fail-open on WP if reclaim cannot run.
- No NATS between RPM and RC.

## Options

### Option 1 — Hostname-only reclaim

- Good, because no key needed after sync.
- Bad, because any caller claiming the hostname could obtain the activation identity.

### Option 2 — licenseKey + clientUuid + hostname, RC sameSubscription, UUID under same subscription

- Good, because clone DB supplies key+uuid as proof of prior account activity; different keys work if same subscription.
- Neutral, because weaker than domain challenge (stolen key+uuid from a DB dump still works).
- Bad, because requires new RC HTTP check and RPM orchestration.

### Option 3 — Domain HTTP / `.well-known` challenge

- Good, because proves control of the hostname without relying on cloned secrets.
- Bad, because heavier UX/ops; out of scope for this delivery.

## Decision Outcome

Chosen option: **"Option 2"**, because it matches the locked product path (resync restore + dual keys) with acceptable security for license activations already visible in the Customer Center to the same account.

### Consequences

- **Reversibility:** two-way door (feature flag disables reclaim; route can evolve).
- **Affected areas:** `api-real-product-manager` reclaim contract, `backends/real-product-manager`, `api-real-commerce` / `backends/real-commerce` same-subscription, WP client stash+persist.
- **Rollback / migration path:** kill switch `REAL_PRODUCT_MANAGER_LICENSE_ACTIVATION_RECLAIM_ENABLED=false` → 503; WP ignores and continues.
- **Positive:** MetaShare-style dual keys; no re-paste on resync when proof holds.
- **Negative:** stolen DB dump of key+uuid remains a reclaim proof for that subscription’s hostnames.

## Validation

E2E: activate staging → resync prod DB onto staging → reclaim restores staging activation; production activation remains; wrong-subscription proof → 422 ProofFailed.

## More Information

- **Related ADRs:** [0001](0001-remove-silent-license-hostname-sync-on-siteurl-update.md), [0003](0003-rc-same-subscription-internal-rpm-only.md)
- **ClickUp:** [CU-869edzz7b](https://app.clickup.com/t/869edzz7b)
- **Diagrams:** \_
