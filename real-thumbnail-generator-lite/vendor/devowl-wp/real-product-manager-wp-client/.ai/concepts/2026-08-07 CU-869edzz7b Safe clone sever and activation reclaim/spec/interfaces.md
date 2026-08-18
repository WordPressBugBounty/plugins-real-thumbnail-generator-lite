# Interfaces & contracts

Phase 3.3 — prose + schema tables only. **No code under `api-packages/` yet** (Phase 4.1).

## Shared principles

- New cross-service HTTP uses **contract-first** (`createContract` + `ContractController`); legacy RPM activation CRUD stays on `routeLocation*` until separately migrated.
- **No NATS** between RPM and RC for this concept.
- WP client talks **only** to Real Product Manager over HTTP; never to Real Commerce.
- Reclaim on WP is **best-effort**: any non-2xx / transport failure is ignored locally.
- Versioning: follow sibling packages — RC/RPM contract-first routes use `versions: ["1.0.0"]` unless a package convention differs (Complyforce uses `v1`; vat-id-check uses `1.0.0` — prefer **`1.0.0`** for RPM/RC consistency with existing license activation path prefix `1.0.0/`).

---

## Sub-project: rc-same-subscription

### Interface: RPM → RC — same subscription check

#### Semantics

| Aspect      | Choice                                                                                                                                                                                                                                                                                                                                                                         |
| ----------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Direction   | Sync                                                                                                                                                                                                                                                                                                                                                                           |
| Pattern     | Request-reply HTTP                                                                                                                                                                                                                                                                                                                                                             |
| Idempotency | Pure read; safe to retry; no side effects                                                                                                                                                                                                                                                                                                                                      |
| Error model | `401` auth fail; `422` validation; `200` with `sameSubscription: false` when either key is unknown or subscriptions differ (do **not** leak which key is missing to anonymous callers — both keys require valid RPM service auth first). Prefer **`sameSubscription: false`** over `404` for unknown keys so RPM can treat “not same” uniformly without branching on NotFound. |
| Schema SoT  | New contract under `api-packages/api-real-commerce/src/route/license/same-subscription/` (exact file name TBD in plan)                                                                                                                                                                                                                                                         |
| Visibility  | **`profiles: ["internal"]`** — excluded from production OpenAPI                                                                                                                                                                                                                                                                                                                |
| Callers     | **Only** `backends/real-product-manager` via typed fetch client + shared secret                                                                                                                                                                                                                                                                                                |

#### Contract (HTTP)

- **Route:** `POST /1.0.0/license/same-subscription`
- **Factory (planned):** `createContractLicenseSameSubscriptionPost`
- **Profiles:** `["internal"]`
- **Guards:** `createContractGuardSuperAdmin` — **not** `JwtGuard`; not a dedicated RPM inbound secret ([ADR 0003](../adrs/0003-rc-same-subscription-internal-rpm-only.md) Option 4)

**Headers**

| Header                                              | Required | Notes                                                                                                                   |
| --------------------------------------------------- | -------- | ----------------------------------------------------------------------------------------------------------------------- |
| `x-authentication-super-admin` (`isSecuritySchema`) | yes      | Compared to `REAL_COMMERCE_SUPER_ADMIN_KEY` / `consumerConfig.auth.superAdminKey`. RPM holds a copy (wired in plan 02). |

**Request**

| Field         | Type        | Required | Notes                                                    |
| ------------- | ----------- | -------- | -------------------------------------------------------- |
| `licenseKeyA` | UUID string | yes      | Proof or target side; order must not matter for equality |
| `licenseKeyB` | UUID string | yes      | Other side                                               |

**Response `200 OK`**

| Field              | Type    | Notes                                                                                                                                                                                                                                                                                                                                                                                                           |
| ------------------ | ------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `sameSubscription` | boolean | `true` iff both keys resolve to non-revoked-or-as-designed License rows sharing the **same** `subscription.id`. Spec lock for Phase 4.1: include revoked keys only if product says reclaim may still prove ownership — **default: require both licenses exist; revoked keys still count for ownership** (subscription membership does not disappear on revoke). Confirm in plan if revoke should force `false`. |

**Error responses**

| Status                     | When                                      |
| -------------------------- | ----------------------------------------- |
| `401 Unauthorized`         | Missing/invalid service secret            |
| `422 Unprocessable Entity` | Schema validation (empty/malformed UUIDs) |

**Idempotency / retries:** RPM may retry on 5xx/timeout with backoff; no DLQ.

**Env:**

- RC: existing `REAL_COMMERCE_SUPER_ADMIN_KEY` (no new catalogue key)
- RPM (plan 02): copy of that value + RC base URL for `RealCommerceService`

---

## Sub-project: rpm-activation-reclaim

### Interface: WP → RPM — reclaim license activation

#### Semantics

| Aspect       | Choice                                                                                                                                                                                                                                         |
| ------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Direction    | Sync                                                                                                                                                                                                                                           |
| Pattern      | Request-reply HTTP                                                                                                                                                                                                                             |
| Idempotency  | Safe to retry: success returns the **existing** activated row for hostname (no new activation insert). Concurrent reclaimers get the same activation identity.                                                                                 |
| Error model  | `503` + code `LicenseActivationReclaimDisabled` when feature flag off; `401/403` if product auth required same as activate; `422` with stable codes for proof failure / no hostname activation; WP **ignores all errors**.                     |
| Schema SoT   | New contract under `api-packages/api-real-product-manager/src/route/license/activation/reclaim/`                                                                                                                                               |
| Visibility   | Public to WP plugins (same exposure class as `POST /license/activation`) — **not** `profiles: ["internal"]` (WordPress must call it). Auth = existing unauthenticated-with-license-key model of activate (knowledge of key+uuid is the proof). |
| Feature flag | `REAL_PRODUCT_MANAGER_LICENSE_ACTIVATION_RECLAIM_ENABLED` default `true`; unset ⇒ enabled                                                                                                                                                      |

#### Contract (HTTP)

- **Route:** `POST /1.0.0/license/activation/reclaim`
- **Factory (planned):** `createContractLicenseActivationReclaimPost`
- **Guards:** none beyond what activate uses today for public license ops (no CompanyGuard — WP does not send company API secret). Optional product id validation in body.

**Request**

| Field               | Type        | Required | Notes                                                                                                                       |
| ------------------- | ----------- | -------- | --------------------------------------------------------------------------------------------------------------------------- |
| `licenseKey`        | UUID string | yes      | Proof key from request-local stash (prod or staging)                                                                        |
| `clientUuid`        | UUID string | yes      | Proof client UUID from stash                                                                                                |
| `hostname`          | string      | yes      | Current WordPress hostname (same encoding rules as client properties today — plain hostname as sent in activate properties) |
| `product.id`        | string      | yes      | Product id (same as activate)                                                                                               |
| `productVariant.id` | string      | yes      | Variant id (same as activate)                                                                                               |

Optional nesting style: flat vs nested `license` / `client` — **prefer flat reclaim-specific body** for clarity (not the full activate tree).

**Response `200 OK`**

| Field               | Type                                                                    | Notes                                                                                                           |
| ------------------- | ----------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------- |
| `licenseActivation` | `ILicenseActivation` (confidential fields stripped as on POST activate) | The **hostname-matched** activation to restore locally — may use a **different** license key than the proof key |

WP must persist: `licenseKey`, `client.uuid`, `type`, `telemetryDataSharingOptIn`, hostname option, clear hint, store activation blob (same as activate success path).

**Error responses (literal codes — exact enum names for Phase 4.1)**

| Status | Code                                  | When                                                                      |
| ------ | ------------------------------------- | ------------------------------------------------------------------------- |
| `503`  | `LicenseActivationReclaimDisabled`    | Feature flag false                                                        |
| `422`  | `LicenseActivationReclaimNotFound`    | No **Activated** activation for product+hostname                          |
| `422`  | `LicenseActivationReclaimProofFailed` | Proof key/uuid fail same-subscription / UUID-under-subscription checks    |
| `422`  | `…`                                   | Product/variant validation failures (reuse existing codes where possible) |

**Server-side proof algorithm (normative for implementers)**

1. If flag off → `503 LicenseActivationReclaimDisabled`.
2. Find **Activated** activation(s) for `product` + client property `hostname` == request hostname (and compatible variant rules as activate). If none → `LicenseActivationReclaimNotFound`.
3. Let `targetKey` = activation.license.licenseKey; `proofKey` = request.licenseKey.
4. Resolve proof client: activation(s) where `client.uuid` == request.clientUuid (any status? **require that this UUID was ever/currently tied to a license under the same subscription** — recommended: find any activation for that UUID, take its licenseKey as `uuidKey`).
5. Call RC `sameSubscription(proofKey, targetKey)` → must be `true`.
6. Call RC `sameSubscription(uuidKey, targetKey)` → must be `true` (or if `uuidKey === proofKey`, one call suffices).
7. If either false → `LicenseActivationReclaimProofFailed`.
8. Return target `licenseActivation` (do not deactivate/create).

**Cache (RPM-internal, not a wire contract):** map fields for license→subscriptionId (or pair results) with field TTL; invalidate fields for keys involved on successful reclaim **and** on successful POST activate.

---

### Interface: RPM → RC — same subscription (consumer side)

#### Semantics

- Same contract as Sub-project 01; RPM is the sole production consumer.
- Wrap in `RealCommerceService.sameSubscription(a, b)` using `createFetchClient` from `@devowl-wp/api-real-commerce`.
- On RC 5xx/timeout: reclaim fails → WP sees non-2xx → ignore (fail-open).
- On RC `sameSubscription: false`: map to `LicenseActivationReclaimProofFailed`.

#### Headers (outbound from RPM)

| Header                         | Value                                                                                                   |
| ------------------------------ | ------------------------------------------------------------------------------------------------------- |
| `x-authentication-super-admin` | `consumerConfig.auth.realCommerce.superAdminKey` ← `REAL_PRODUCT_MANAGER_REAL_COMMERCE_SUPER_ADMIN_KEY` |

---

## Sub-project: wp-client-sever-and-reclaim

### Interface: Local sever → optional reclaim (in-process + HTTP)

#### Semantics

| Aspect         | Choice                                        |
| -------------- | --------------------------------------------- |
| Direction      | Sync (HTTP reclaim after local sever)         |
| Pattern        | Best-effort request-reply; failures swallowed |
| Idempotency    | Server-side; client may call once per sever   |
| Cross-boundary | Only RPM reclaim HTTP; no RC                  |

#### Contract (client behaviour — not an API package)

1. On host mismatch (existing sever guards): **stash** `{ licenseKey, clientUuid }` in request memory **before** clearing options / UUID.
2. Perform local sever as today (`deactivate(false)`, clear UUID, etc.).
3. If stash incomplete → skip reclaim.
4. Else `POST …/license/activation/reclaim` with stash + current hostname + product/variant.
5. On **2xx** with `licenseActivation`: run shared persist helper (same options as activate success).
6. On **any** other outcome: do nothing reclaim-specific; continue `activateProgrammatically` / existing hints.
7. Remove `update_option_siteurl` hostname rewrite (no remote interface).

**HTTP client method (planned):** `client\LicenseActivation::postReclaim(...)` → path `1.0.0/license/activation/reclaim`.

---

## Diagrams

Skipped (two sync hops, linear proof; text algorithm above is enough). Optional sequence for peer-check if desired.

## ADR candidates (surface only — not scaffolded)

None blocking. Optional later: “revoked license keys still prove subscription membership” if product wants a formal record — default above is include revoked for ownership.
