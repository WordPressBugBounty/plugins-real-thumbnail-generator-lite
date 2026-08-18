---
status: accepted
created: 2026-08-07
supersedes:
superseded-by:
tags: [backend, real-commerce, security]
---

# RC same-subscription route is internal and RPM-only

## Context and Problem Statement

Real Product Manager needs to know whether two license keys share a Real Commerce subscription, but RPM must not import RC TypeScript or duplicate subscription rows. How should the HTTP surface be exposed and authenticated?

## Decision Drivers

- Subscription ownership is RC’s responsibility.
- WordPress and public clients must never call this check.
- Align with existing contract-first `profiles: ["internal"]` and shared-secret service auth.
- No NATS between RPM and RC.
- Prefer reusing an existing RC service secret over inventing a second RPM↔RC key pair when the caller is already a trusted backend.

## Options

### Option 1 — Public or JWT Customer Center route

- Good, because reuses existing JwtGuard patterns.
- Bad, because exposes subscription-membership oracle to end-user tokens; wrong audience.

### Option 2 — `profiles: ["internal"]` + dedicated RPM shared-secret guard

- Good, because least privilege: only RPM holds an inbound-only secret; mirrors reverse of RC→RPM `x-authentication` company secret.
- Neutral, because new env secret pair to catalogue and rotate in **both** `/backends/real-commerce` and `/backends/real-product-manager`.
- Bad, because URL remains reachable if secret leaks (mitigate with rotation + network posture); ops overhead for a single internal boolean oracle.

### Option 3 — Embed subscription id in RPM license metadata

- Good, because no cross-call at reclaim time.
- Bad, because duplicates RC truth and drifts on plan/transfer changes.

### Option 4 — `profiles: ["internal"]` + existing `SuperAdminContractGuard`

- Good, because no new Infisical key; RPM already (or will) call RC with a known shared secret pattern; `x-authentication-super-admin` / `REAL_COMMERCE_SUPER_ADMIN_KEY` already gates other internal RC routes.
- Neutral, because RPM receives RC’s super-admin capability if compromised (blast radius larger than Option 2).
- Bad, because conflates “admin tooling” and “RPM service” identities on one key.

## Decision Outcome

Chosen option: **"Option 4"** (amended 2026-08-07 during implementation of plan 01), because for this internal boolean check the ops cost of a dedicated inbound secret outweighs the least-privilege gain; `profiles: ["internal"]` plus SuperAdmin is sufficient trust for RPM→RC.

Original conception chose Option 2; implementation deliberately simplified to Option 4 with product sign-off.

### Consequences

- **Reversibility:** two-way door (swap guard back to a dedicated RPM secret without changing request/response schema).
- **Affected areas:** `api-packages/api-real-commerce` same-subscription contract (`createContractGuardSuperAdmin`), `backends/real-commerce` controller, `backends/real-product-manager` `RealCommerceService` (sends `x-authentication-super-admin`).
- **Rollback / migration path:** disable route or rotate `REAL_COMMERCE_SUPER_ADMIN_KEY`; RPM reclaim fails open.
- **Positive:** zero new secrets; clear service boundary; no WP→RC edge; OpenAPI-internal profile.
- **Negative:** RPM must hold RC super-admin key (copy under RPM Infisical path in plan 02); compromise of RPM widens RC blast radius to all SuperAdmin routes.

## Validation

Wrong / missing `x-authentication-super-admin` → 401; valid key → 200 boolean; route absent from production public OpenAPI profile; WP package has no RC HTTP client for reclaim.

## More Information

- **Related ADRs:** [0002](0002-subscription-scoped-activation-reclaim-proof.md)
- **ClickUp:** [CU-869edzz7b](https://app.clickup.com/t/869edzz7b)
- **Diagrams:** \_
