# backends/real-commerce — license → subscription

## Source paths

- `backends/real-commerce/src/entity/license.ts`
- `backends/real-commerce/src/entity/subscription.ts`
- `backends/real-commerce/src/service/subscription.ts`
- `backends/real-commerce/src/controller/1.0.0/license/`
- `backends/real-commerce/src/service/real-product-manager.ts`

## Responsibility

Owns the commerce-side mapping of RPM `licenseKey` (UUID PK) to a `Subscription` (and plan/RPM variant), plus customer-center license CRUD that loads that relation and calls RPM over HTTP for issuance / activation counts / revocation. Does **not** store activation hostname or client UUID (those live in Real Product Manager); does not yet expose a dedicated same-subscription reclaim endpoint.

## Public surface

- `License` entity — `licenseKey` primary key, `ManyToOne` `subscription`, `productPlanRpmProductVariant`, `isRevoked`, optional `description`.
- `SubscriptionService.findActiveLicensesFromSubscription` — licenses for a subscription with `isRevoked: false`.
- License controllers under `controller/1.0.0/license/` — list / create / patch / delete keys for the JWT user; load `License` with `subscription` (+ user ownership check).
- `RealProductManagerService` — sync HTTP to RPM (`createLicenses`, `readLicense(s)`, `readLicenseActivations`, revoke-via-patch); used whenever commerce needs RPM truth.
- Inline lookups — `orm.findOne(License, { where: { licenseKey }, relations: { subscription: … } })` (e.g. support ticket product resolution, license.patch).

## Data flow

1. Checkout / Paddle completion issues RPM licenses then persists `new License(rpmLicense.licenseKey, subscription, rpmProductVariant, …)`.
2. Customer-center reads: JWT → user’s subscriptions → `License` rows by `subscription.id` → enrich from RPM.
3. Ownership check pattern: find by `licenseKey` + `isRevoked: false`, require `license.subscription.user.id === user.id`.
4. Same-subscription membership for two keys is: load both `License` rows and compare `subscription.id` (no dedicated helper today).
5. Cancellation path: subscription webhook sets pending; later schedulable revokes commerce licenses and tells RPM to revoke keys — activations themselves remain RPM’s concern.

## Key types / contracts

- `License.licenseKey` — UUID string, same value as RPM license key.
- `License.subscription` — TypeORM relation; source of truth for “same subscription”.
- `ILicense` from `@devowl-wp/api-real-commerce` — API shape for the entity.
- `ESubscriptionStatus` — used when listing licenses / filtering active commerce state.
- RPM bridge types via `@devowl-wp/api-real-product-manager` fetch client inside `RealProductManagerService`.

## Non-obvious constraints

- Commerce `License` is a **thin pointer** (key + subscription + variant); installation/activation state is always fetched from RPM when needed.
- Revoke in commerce sets `isRevoked: true`; RPM revoke is a separate HTTP patch (`isRevoked`), not DELETE of the license row in RPM.
- Multi-website plans often start with **one** master key; additional keys are created later under the same subscription — same-subscription checks must not assume one key per subscription.
- No message-broker contract between RC and RPM for licenses — synchronous HTTP only.
- There is no shared “licenseKey → subscriptionId” cache in this package yet; framing intends `@devowl-wp/kv-cache` hashmap with per-field TTL for reclaim.

## Known pitfalls

- Looking up RPM activations by key alone does not prove commerce ownership; always join/compare `License.subscription` in RC for same-subscription proof.
- `findActiveLicensesFromSubscription` filters `isRevoked: false` only — cancelled-but-not-yet-revoked subscriptions may still surface active license rows until the schedulable runs.
- Support `license-lookup` is Reamaze-SSO gated and ticket-scoped; do not reuse it as the reclaim internal API.
- Detach loaded relations before responses when they were only needed for auth/derivation (controller hygiene in this backend).

## Pointers

- Sibling: `./backends-real-product-manager--license-activation.md`
- Sibling: `./api-real-commerce--contract-first-http.md`
- Sibling: `./backend-packages-kv-cache--map-field-ttl.md`
- Package notes: `backends/real-commerce/CLAUDE.md` (license issuance / revocation section)
