# backends/real-product-manager — license activation

## Source paths

- `backends/real-product-manager/src/controller/1.0.0/license/activation/`
- `backends/real-product-manager/src/entity/license/activation/activation.ts`
- `backends/real-product-manager/src/entity/license/license.ts`
- `backends/real-product-manager/src/entity/client/client.ts`
- `backends/real-product-manager/src/entity/client/property.ts`

## Responsibility

Owns HTTP create / read / patch / delete / list of **license activations** (client install bindings), including hostname-scoped auto-deactivate of prior activations for the same license+type, max-installation enforcement, client UUID create-or-reuse, and deferred property/telemetry patches via schedulables. Does **not** own WordPress option persistence, Real Commerce subscription ownership, or same-subscription reclaim (not present yet).

## Public surface

- `LicenseActivationPostController` — `POST /1.0.0/license/activation`; create activation, optionally generate free-product license / new client UUID.
- `LicenseActivationGetController` — `GET` by `licenseKey` + `clientUuid`; nulled-version trolling branch.
- `LicenseActivationPatchController` — `PATCH` telemetry / client / activation properties (often deferred to schedulables).
- `LicenseActivationDeleteController` — soft-deactivate activation (`status` + `deactivatedAt`) for licenseKey + client uuid.
- `LicenseActivationsGetController` — list activations for a licenseKey (optional status filter).
- Entities `LicenseActivation`, `License`, `Client`, `ClientProperty`, `LicenseActivationProperty`.

## Data flow

1. WP client POST → validate product/variant, resolve or mint `License` by key, build `Client` (reuse uuid or generate), attach client properties (must include hostname for host matching).
2. `findAlreadyExistingLicenseActivationForThisHostToDeactivate` loads activated rows for same license + type; matching `EClientPropertyKey.Hostname` rows flip to deactivated before insert.
3. `isMaxUsageOfLicenseReached` counts remaining activated slots (prod vs development caps), excluding rows about to deactivate.
4. Transaction saves deactivated rows, inserts client (TypeORM relation workaround), inserts activation; newsletter signup is fire-and-forget after commit.
5. GET/PATCH/DELETE resolve `License` + `Client` by key/uuid, then the `LicenseActivation` row with status `Activated` (errors like `LicenseActivationNotFound`, expired/revoked license).
6. `@AfterLoad` on activation merges deferred client/activation/telemetry property caches into the entity for responses.

## Key types / contracts

- `ELicenseActivationType` — `production` | `development`.
- `ELicenseActivationStatus` — `activated` | `deactivated`.
- `EClientPropertyKey.Hostname` — load-bearing for same-host replace on POST.
- Legacy API types — `IRouteRequestLicenseActivationPost` / Get / Patch / Delete and `routeLocationLicenseActivation*`.
- Error codes — `LicenseMaxUsagesReached`, `LicenseNotFound`, `ClientNotFound`, `LicenseActivationNotFound`, `LicenseHasBeenExpired`, `LicenseHasBeenRevoked`, plus entity validation codes.

## Non-obvious constraints

- Controllers use **legacy** `@Route` + `ControllerPost`/`Get`/… with `IRoute*` types — not `ContractController` / `createContract`.
- Same-host deactivate is scoped to **one license id + activation type**, not cross-license / subscription.
- Hostname match is exact string equality on client property value; missing hostname on the pending activation yields empty deactivate list.
- Free products may mint a new license in-POST; paid path requires existing licenseKey matching productVariant.
- Confidential fields stripped before response (`removeConfidentialProperties`); email property filtered from activation properties.
- PATCH may enqueue deferred property writers rather than always writing properties synchronously.

## Known pitfalls

- DELETE is soft-deactivate, not hard delete; WP client treating remote DELETE as “gone forever” still maps to deactivated status + GET miss → `LicenseActivationNotFound`.
- Reusing a cloned client UUID on POST/PATCH/DELETE addresses the **same** client row as production; sever/reclaim must not call DELETE with that UUID against prod’s activation unintentionally.
- Max-usage messaging points users at Customer Center; reclaim/same-subscription logic is not implemented in these controllers today.
- Nulled-version GET can return teapot / spoofed `LicenseActivationNotFound` — do not treat that path as a normal activation miss for reclaim.

## Pointers

- Sibling: `./api-real-product-manager--license-activation.md`
- Sibling: `./wordpress-packages-real-product-manager-wp-client--license.md`
- Sibling: `./backends-real-commerce--license-subscription.md`
- Skill (new routes only): `.claude/skills/contract-first-http/SKILL.md`
