---
status: done
sub-project: rpm-activation-reclaim
index: "02"
depends-on: [rc-same-subscription]
---

# 02 — RPM activation reclaim

## Goal

Add contract-first `POST /1.0.0/license/activation/reclaim` on Real Product Manager: find Activated activation by hostname, prove proof `licenseKey` + `clientUuid` via RC `sameSubscription`, return target activation. Feature flag default on (503 when off). Cache RC lookups with field TTL; invalidate on activate/reclaim success.

Honours: [spec/interfaces.md](../spec/interfaces.md) § rpm-activation-reclaim · [spec/boundaries.md](../spec/boundaries.md) § rpm-activation-reclaim · proof algorithm in interfaces.md.

## Depends on

- **01** deployed/reachable: RC same-subscription + SuperAdmin auth + RC base URL in RPM config.

## Out of scope

- Migrating legacy activate CRUD to contract-first.
- WP client changes (03).
- Freemius-style notices.

## Tasks

### T1 — Env: reclaim flag + RC client config

Use [`add-environment-variable`](../../../../../../.claude/skills/add-environment-variable/SKILL.md) for each new key.

- `REAL_PRODUCT_MANAGER_LICENSE_ACTIVATION_RECLAIM_ENABLED` — default **true** when unset; wire `config/{env}/feature.ts` (+ types)
- RPM outbound to RC: base URL + copy of `REAL_COMMERCE_SUPER_ADMIN_KEY` for `x-authentication-super-admin` (ADR 0003 Option 4; no dedicated inbound secret)
- Wire `consumerConfig.auth.realCommerce` (or equivalent) in RPM config profiles
- Compose `environment:` if needed → note `dowl docker:purge && dowl docker:start` for developer after change

**Verification:** `.env-default` blocks exist; config types compile; flag reads false only when explicitly `"false"`.

### T2 — Reclaim contract in `api-real-product-manager`

Invoke **`contract-first-http`**.

- `api-packages/api-real-product-manager/src/route/license/activation/reclaim/license-activation-reclaim.post.ts`
    - `createContractLicenseActivationReclaimPost`
    - `versions: ["1.0.0"]`, path `/license/activation/reclaim`, `POST`
    - **No** `profiles: ["internal"]` (WP must call)
    - Request: `licenseKey`, `clientUuid`, `hostname`, `product.id`, `productVariant.id` (UUIDs / strings per existing product id style)
    - Response `200`: `{ licenseActivation: … }` deriving from existing activation entity schema / confidential stripping notes
    - Literal errors: `503 LicenseActivationReclaimDisabled`, `422 LicenseActivationReclaimNotFound`, `422 LicenseActivationReclaimProofFailed`
- Export from barrels; contract tests

**Verification:** eslint + Vitest in `api-packages/api-real-product-manager`.

### T3 — `RealCommerceService` + field-TTL cache

- `backends/real-product-manager/src/service/real-commerce.ts`
    - `createFetchClient` from `@devowl-wp/api-real-commerce`
    - `sameSubscription(licenseKeyA, licenseKeyB): Promise<boolean>`
    - On non-200 / transport error: throw `ServiceError` (reclaim maps to proof fail or 5xx)
- Cache via `@devowl-wp/kv-cache`:
    - Prefer caching `licenseKey → subscriptionId` if RC later exposes it; **until then** cache is optional enhancement on boolean pairs **or** implement cache after a thin internal resolve — **minimum for this task:** call RC each time **or** cache `sameSubscription` results with short field TTL keyed by sorted `min(a,b):max(a,b)`
    - Spec prefers membership cache: if boolean-only is kept, document in PR; prefer extending RC response later only if needed — **do not** expand RC contract in this plan without spec change
    - Use `singleFlightCompute` for concurrent reclaim stampede
    - `mapDelete` / field clear helper for invalidation

**Verification:** unit tests with mocked fetch client + cache adapter; eslint.

### T4 — Reclaim controller + proof service

- `backends/real-product-manager/src/controller/1.0.0/license/activation/reclaim/license-activation-reclaim.post.ts` — `ContractController`
- Service helper implementing interfaces.md algorithm:
    1. Flag off → 503 `LicenseActivationReclaimDisabled`
    2. Find Activated by product + hostname client property
    3. Resolve `uuidKey` from activation(s) for proof `clientUuid`
    4. RC `sameSubscription(proofKey, targetKey)` and `sameSubscription(uuidKey, targetKey)` (dedupe if equal)
    5. Fail → `LicenseActivationReclaimProofFailed`; success → return target activation (no insert/deactivate)
- Register barrel
- Vitest covering: flag off, not found, proof fail, success, same key+uuid happy path

**Verification:** `dowl test:vitest` in RPM package for new files; eslint.

### T5 — Invalidate cache on successful POST activate

- In existing `LicenseActivationPostController` success path (and reclaim success): invalidate cache fields for involved license keys
- Keep change minimal — call shared invalidate helper

**Verification:** unit test that activate success calls invalidate; no behaviour change to activate happy path otherwise.

### T6 — Manual smoke (with 01)

- Flag true: reclaim with staging hostname + prod proof key/uuid from same subscription → 200 + staging activation payload
- Flag false: 503
- Wrong proof: 422 ProofFailed
- Prod activation untouched (no DELETE)

**Verification:** NR/logs or local APM optional; checklist in PR.
