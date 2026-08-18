# Architectural boundaries

Per-sub-project placement against monorepo tiers and `.dependency-cruiser.cjs`. Assembled in Phase 3.2.

## What lands where (overview)

| Location                                                 | What                                                                                                    |
| -------------------------------------------------------- | ------------------------------------------------------------------------------------------------------- |
| **`api-packages/api-real-commerce/`**                    | Contract-first `sameSubscription` route factory + inferred types                                        |
| **`backends/real-commerce/`**                            | `ContractController` for same-subscription; reads `License` → `Subscription` in RC DB                   |
| **`api-packages/api-real-product-manager/`**             | Contract-first `POST /license/activation/reclaim` factory + types (legacy activation CRUD stays legacy) |
| **`backends/real-product-manager/`**                     | Reclaim controller/service; RC HTTP client; kv-cache subscription lookup; feature flag                  |
| **`wordpress-packages/real-product-manager-wp-client/`** | Sever stash, reclaim HTTP call, shared persist helper; remove `update_option_siteurl` hook              |
| **Not created**                                          | New `*-packages/` tier; NATS broker between RPM and RC; RC import from RPM; WP client → RC              |

---

## Sub-project: rc-same-subscription

Real Commerce owns subscription truth; RPM consumes it over HTTP only.

### Modules / services / layers

| Name                         | Path                                                                                          | Tier            | Responsibility                                                                                                         | Why this tier                                                                                                        |
| ---------------------------- | --------------------------------------------------------------------------------------------- | --------------- | ---------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------- |
| Same-subscription contract   | `api-packages/api-real-commerce/src/route/license/same-subscription/` (exact slug TBD in 3.3) | `api-packages/` | Zod contract: request `{ licenseKeyA, licenseKeyB }`, response `{ sameSubscription: boolean }`                         | Cross-backend HTTP surface owned by RC; consumable from RPM via typed fetch client without importing RC backend code |
| Same-subscription controller | `backends/real-commerce/src/controller/1.0.0/license/same-subscription/`                      | `backends/`     | Resolve both keys on `License` entity with `subscription` relation; compare subscription IDs; 404/422 when key unknown | Subscription ownership lives in RC DB only                                                                           |
| (optional) thin service      | `backends/real-commerce/src/service/license-subscription.ts`                                  | `backends/`     | Reusable lookup if controller would otherwise duplicate `License` queries                                              | Keep controller thin; no promotion to `backend-packages/` for one endpoint                                           |

**Auth / visibility:** Contract uses `profiles: ["internal"]` (excluded from production OpenAPI). Guard: existing `SuperAdminContractGuard` / `x-authentication-super-admin` — **not** `JwtGuard`, not a dedicated RPM inbound secret ([ADR 0003](../adrs/0003-rc-same-subscription-internal-rpm-only.md)). Sole intended caller: Real Product Manager (holds a copy of `REAL_COMMERCE_SUPER_ADMIN_KEY`). WordPress must never call this route.

**Explicitly not here:** Storing client UUID; activation hostname lookup; cache (RPM owns cache of RC responses).

### Tier graph (text form)

```
api-real-commerce (contract)
  → consumed by backends/real-commerce (ContractController)
  → consumed by backends/real-product-manager (createFetchClient HTTP only)

backends/real-commerce License entity + Subscription relation
  → read inside same-subscription controller only
```

### Dependency-cruiser cross-check

- `api-real-commerce` must not import `backends/*` or `backend-packages/*` beyond allowed api deps — contract file only.
- `backends/real-commerce` may import `@devowl-wp/api-real-commerce`, `@devowl-wp/backend`, local entities — no import of `backends/real-product-manager`.
- No new shared package required.

---

## Sub-project: rpm-activation-reclaim

Real Product Manager owns activation rows and reclaim orchestration; delegates subscription equality to RC.

### Modules / services / layers

| Name                      | Path                                                                                                     | Tier                                | Responsibility                                                                                                                                                              | Why this tier                                                              |
| ------------------------- | -------------------------------------------------------------------------------------------------------- | ----------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------- |
| Reclaim contract          | `api-packages/api-real-product-manager/src/route/license/activation/reclaim/`                            | `api-packages/`                     | Contract-first POST reclaim: proof body + success `licenseActivation` payload (same shape as activate response) + error codes incl. `LicenseActivationReclaimDisabled`      | WP client and RPM controller share types; reclaim is RPM-owned HTTP        |
| Reclaim controller        | `backends/real-product-manager/src/controller/1.0.0/license/activation/reclaim/`                         | `backends/`                         | Feature-flag gate (503); find activated row by hostname+product; UUID proof via existing Client/Activation repos; call RC `sameSubscription`; return activation for restore | Side-effecting service endpoint                                            |
| Reclaim service / helper  | `backends/real-product-manager/src/service/license-activation-reclaim.ts` (or colocated private methods) | `backends/`                         | Orchestration: hostname activation lookup, proof validation, RC call                                                                                                        | Keeps controller thin                                                      |
| Real Commerce HTTP client | `backends/real-product-manager/src/service/real-commerce.ts`                                             | `backends/`                         | `createFetchClient` from `@devowl-wp/api-real-commerce`; `sameSubscription(a,b)` wrapper                                                                                    | **First RPM→RC HTTP client**; no code import from `backends/real-commerce` |
| Subscription lookup cache | inline in `real-commerce.ts` or `helper/license-subscription-cache.ts` under RPM                         | `backends/` + `@devowl-wp/kv-cache` | Map `licenseKey → subscriptionId` (or cache boolean pairs) with `fieldTtls`; `singleFlightCompute` on miss; `mapDelete` field on successful activate/reclaim                | RC membership is read-heavy; cache stays in RPM consumer                   |
| Feature flag              | `backends/real-product-manager/src/config/{env}/feature.ts` + `.env-default`                             | `backends/`                         | `REAL_PRODUCT_MANAGER_LICENSE_ACTIVATION_RECLAIM_ENABLED` default true                                                                                                      | Kill switch without deploy                                                 |
| Cache invalidation hook   | call sites in existing `LicenseActivationPostController` + reclaim success path                          | `backends/`                         | Invalidate cached RC fields for keys touched                                                                                                                                | Avoid stale negative cache after manual activate                           |

**Explicitly not here:** Subscription entity duplication in RPM; moving reclaim logic to RC; migrating legacy POST activate to contract-first.

### Tier graph (text form)

```
api-real-product-manager (reclaim contract)
  → backends/real-product-manager (ContractController + service)
  → consumed by wordpress-packages/real-product-manager-wp-client (raw HTTP 1.0.0 path)

backends/real-product-manager
  → HTTP → backends/real-commerce (sameSubscription) via api-real-commerce client only
  → @devowl-wp/kv-cache (existing backend-packages dep)
  → local TypeORM entities (LicenseActivation, Client, ClientProperty)

wordpress-packages/real-product-manager-wp-client
  → HTTP → RPM reclaim only (never RC)
```

### Dependency-cruiser cross-check

- `backends/real-product-manager` importing `@devowl-wp/api-real-commerce` is allowed (backend → api-package).
- Must **not** add `backends/real-product-manager` → `backends/real-commerce` TypeScript import; HTTP only.
- `api-real-product-manager` must not import `api-real-commerce` or encode RC types — RPM backend orchestrates both.
- `@devowl-wp/kv-cache` already in backend tier; no new package.

---

## Sub-project: wp-client-sever-and-reclaim

WordPress client changes stay inside the shared WP package; no new tiers.

### Modules / services / layers

| Name                  | Path                                                                                 | Tier                  | Responsibility                                                                                                                 | Why this tier                             |
| --------------------- | ------------------------------------------------------------------------------------ | --------------------- | ------------------------------------------------------------------------------------------------------------------------------ | ----------------------------------------- |
| Sever + stash         | `wordpress-packages/real-product-manager-wp-client/src/license/License.php`          | `wordpress-packages/` | Request-local stash of key+uuid before option clear; remove `update_option_siteurl` hook registration + method (or gut method) | Domain logic already lives here           |
| Reclaim HTTP          | `wordpress-packages/real-product-manager-wp-client/src/client/LicenseActivation.php` | `wordpress-packages/` | `postReclaim(...)` → `1.0.0/license/activation/reclaim`                                                                        | Symmetric with existing POST/PATCH client |
| Reclaim orchestration | `License.php` `validateNewHostName()` / sever path                                   | `wordpress-packages/` | After sever: best-effort reclaim; on any failure ignore; then existing programmatic + hints                                    | Fail-open per spec                        |
| Persist from remote   | `LicenseActivation.php` (extract `persistLocalLicenseActivationFromRemote`)          | `wordpress-packages/` | Shared by activate + reclaim success paths                                                                                     | Avoid duplicate option writes             |
| Tests                 | `wordpress-packages/real-product-manager-wp-client/test/`                            | package test          | Sever stash + reclaim ignore-on-error behaviour                                                                                | Vitest/PHPCS per package norms            |

**Explicitly not here:** RC calls; subscription logic; feature flag (RPM only); changes to consuming plugins beyond semver bump of wp-client package.

### Tier graph (text form)

```
wordpress-packages/real-product-manager-wp-client
  → HTTP → RPM (activate, reclaim, sync, …)
  → consumed by wordpress-plugins/* via composer dependency

No new edges to api-packages or backends from PHP (HTTP only).
```

### Dependency-cruiser cross-check

- PHP package does not participate in dependency-cruiser TS graph.
- TS parts of wp-client (if any) must not import backend tiers — unchanged.
- Consuming plugins must not fork license logic; they receive behaviour via package update.

---

## Cross-sub-project sequencing (boundary-relevant)

1. **01 rc-same-subscription** — RC contract + route must exist before RPM can integrate the client (02).
2. **02 rpm-activation-reclaim** — reclaim route + RC client before WP calls reclaim (03).
3. **03 wp-client-sever-and-reclaim** — can ship sever/hook removal independently but reclaim HTTP requires 02 deployed.

Shared secrets (RC accepts RPM, RPM URL for RC) are **two env catalogue entries**, one per backend — not a new package.
