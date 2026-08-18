---
status: accepted
created: 2026-08-07
supersedes:
superseded-by:
tags: [wordpress, real-product-manager-wp-client]
---

# Remove silent license hostname sync on siteurl update

## Context and Problem Statement

`License::update_option_siteurl` rewrote the persisted license hostname whenever WordPress updated `siteurl`. That fixed intentional Settings → General renames (CU-g150eg) but also ran for WP-CLI / Toolkit / migration `update_option('siteurl')`, erasing the clone signal so `probablySeverClonedLicenseIdentity` never fired and staging could remote-DELETE production. How should hostname persistence behave on `siteurl` change?

## Decision Drivers

- Staging clones must keep a hostname mismatch signal until sever/reclaim runs.
- Intentional domain rename without programmatic filter may require re-activation (acceptable).
- Prefer fail-safe for production over silent convenience for rename.
- No Freemius-style notice in this delivery.

## Options

### Option 1 — Sharpen hook to Settings → General only

- Good, because CU-g150eg rename via UI keeps working without re-activate.
- Neutral, because request detection (`option_page === general`) is fragile across WP versions/tools.
- Bad, because any tool that fakes the Settings request still hides clones; still more magic than Freemius.

### Option 2 — Remove the hook entirely

- Good, because every siteurl change that leaves persisted hostname stale becomes a detectable mismatch; clones stay visible.
- Neutral, because Settings rename falls back to sever + manual/programmatic activate (same as reclaim fail-open).
- Bad, because CU-g150eg convenience is lost for customers without programmatic keys.

### Option 3 — Freemius-style admin notice (migrate / temporary / long-term)

- Good, because user intent is explicit.
- Bad, because out of scope for this delivery; does not alone restore after resync.

## Decision Outcome

Chosen option: **"Option 2"**, because clone safety outweighs silent rename convenience; reclaim + programmatic filter cover the common agency paths.

### Consequences

- **Reversibility:** two-way door (re-add a sharpened hook later if rename UX regresses).
- **Affected areas:** `wordpress-packages/real-product-manager-wp-client` (`License.php`).
- **Rollback / migration path:** restore hook or Settings-only variant behind a follow-up ADR.
- **Positive:** prerelease root cause (silent hostname rewrite) eliminated.
- **Negative:** pure Settings→General renames need re-activate or reclaim if proof still holds.

## Validation

Clone reproduction: after siteurl rewrite tools, persisted hostname still differs from current host → sever runs; no remote DELETE with cloned UUID (New Relic / access logs).

## More Information

- **Related ADRs:** [0002](0002-subscription-scoped-activation-reclaim-proof.md), [0003](0003-rc-same-subscription-internal-rpm-only.md)
- **ClickUp:** [CU-869edzz7b](https://app.clickup.com/t/869edzz7b), origin CU-g150eg
- **Diagrams:** \_
