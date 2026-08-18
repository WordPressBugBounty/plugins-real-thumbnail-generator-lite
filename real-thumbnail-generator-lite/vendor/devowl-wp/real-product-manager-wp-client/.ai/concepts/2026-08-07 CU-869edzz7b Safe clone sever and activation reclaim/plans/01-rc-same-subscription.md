---
status: done
sub-project: rc-same-subscription
index: "01"
depends-on: []
---

# 01 — RC same-subscription (internal)

## Goal

Expose an **internal-only** Real Commerce HTTP endpoint that answers whether two RPM license keys belong to the same subscription. Sole caller: Real Product Manager.

Honours: [spec/interfaces.md](../spec/interfaces.md) § rc-same-subscription · [spec/boundaries.md](../spec/boundaries.md) § rc-same-subscription · [ADR 0003](../adrs/0003-rc-same-subscription-internal-rpm-only.md) (Option 4 — SuperAdmin).

## Out of scope

- Client UUID / hostname / activation logic (RPM).
- Caching (RPM consumer).
- Public Customer Center or JWT access.
- NATS.
- New Infisical secret (reuse `REAL_COMMERCE_SUPER_ADMIN_KEY`).

## Tasks

### T1 — Auth: reuse SuperAdmin (no new env)

- **Do not** add `REAL_COMMERCE_REAL_PRODUCT_MANAGER_INBOUND_API_SECRET`.
- Auth is existing `consumerConfig.auth.superAdminKey` / `REAL_COMMERCE_SUPER_ADMIN_KEY`.
- Plan 02 wires the **same** value into RPM so it can send `x-authentication-super-admin`.

**Verification:** no new auth fields on `IConfigAuth.realProductManager`; IDE clean.

### T2 — Contract-first route in `api-real-commerce`

Invoke **`contract-first-http`** at execution time.

- Reuse `createContractGuardSuperAdmin` (`x-authentication-super-admin`)
- `api-packages/api-real-commerce/src/route/license/same-subscription/same-subscription.post.ts` — `createContractLicenseSameSubscriptionPost`:
    - `versions: ["1.0.0"]`
    - `path: "/license/same-subscription"`, `POST`
    - `profiles: ["internal"]`
    - `guards: { superAdmin: createContractGuardSuperAdmin() }`
    - request: `{ licenseKeyA: z.uuid(), licenseKeyB: z.uuid() }`
    - response `200`: `{ sameSubscription: z.boolean() }`
- Export from package barrel / route index

**Verification:** `dowl lint:eslint` in `api-packages/api-real-commerce`; OpenAPI / profile checks show route internal.

### T3 — `ContractController` in `backends/real-commerce`

- Reuse `SuperAdminContractGuard` (no new guard file)
- `backends/real-commerce/src/controller/1.0.0/license/same-subscription/same-subscription.post.ts` — `@Contract` + `ContractController`
    - Load both `License` rows with `subscription` relation by `licenseKey`
    - Missing either key → `200 { sameSubscription: false }` (per interfaces.md)
    - Both found → compare `subscription.id`; revoked keys **still count** for ownership
- Register in controller barrel
- Smoke checklist under `references/` (RC has no Vitest harness yet)

**Verification:** `dowl lint:eslint` in `backends/real-commerce`.

### T4 — Smoke against running stack (dev)

With RC up and `REAL_COMMERCE_SUPER_ADMIN_KEY` available:

- Call with wrong secret → 401
- Call with valid secret + two keys from same subscription fixture → `sameSubscription: true`
- Different subscriptions / unknown → `false`

**Verification:** [references/01-rc-same-subscription-smoke.md](../references/01-rc-same-subscription-smoke.md); no stack-local UUIDs in committed docs.
