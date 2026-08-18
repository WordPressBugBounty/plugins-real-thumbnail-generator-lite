# api-packages/api-real-product-manager — license activation routes

## Source paths

- `api-packages/api-real-product-manager/src/route/license/activation/`
- `api-packages/api-real-product-manager/src/entity/license/activation/activation.ts`
- `api-packages/api-real-product-manager/src/entity/license/license.ts`
- `api-packages/api-real-product-manager/src/entity/client/client.ts`
- `api-packages/api-real-product-manager/src/entity/client/property.ts`

## Responsibility

Defines the shared TypeScript route locations, request/response interfaces, and entity enums for RPM license activation HTTP, consumed by `backends/real-product-manager` controllers and by typed clients (WP client uses raw HTTP shapes; commerce uses fetch client for admin/support flows). Does **not** implement handlers; does **not** use Zod `createContract` for these activation routes (legacy `routeLocation*` + `IRoute*` pattern).

## Public surface

- `routeLocationLicenseActivationPost` / `Get` / `Patch` / `Delete` — path `/license/activation`, verbs POST/GET/PATCH/DELETE.
- `routeLocationLicenseActivationsGet` — path `/license/activations`, GET list.
- `IRouteRequestLicenseActivationPost` — nested `licenseActivation` with product/variant ids, optional licenseKey, client uuid/properties, type, telemetry/newsletter, ip, properties.
- `IRouteParamsLicenseActivationGet` — query `licenseKey` + `clientUuid`.
- `IRouteRequestLicenseActivationDelete` — body licenseKey + client uuid.
- `ILicenseActivation`, `ELicenseActivationType`, `ELicenseActivationStatus`.
- `EClientPropertyKey` (includes `Hostname`) and activation/client property key enums.
- Stub helpers `createLicenseActivationGetContract` / `Delete` / `ActivationsGet` — `ContractFromLegacyRoute` factories returning `undefined` (typing bridge only).

## Data flow

1. Backend registers controllers against `routeLocation*` (version `1.0.0` on the controller `@Route`).
2. WP HTTP client posts/patches/gets/deletes shapes matching these interfaces (property bags as `{ key, value }[]`).
3. Successful POST/GET/PATCH responses carry `licenseActivation: ILicenseActivation` (full nested license + client).
4. List GET returns `licenseActivations[]` filtered by licenseKey (+ optional status).
5. Consumers of `@devowl-wp/api-real-product-manager` fetch client depend on these exports; changing shapes requires updating backend controllers and any generated/typed clients in lockstep.

## Key types / contracts

- `ILicenseActivation` — id, license, client, type, status, activatedAt/deactivatedAt, telemetry/newsletter flags, properties, telemetry[], ip.
- `ELicenseActivationType` — `production` | `development`.
- `ELicenseActivationStatus` — `activated` | `deactivated`.
- `IUnsavedClientProperties` / `IUnsavedLicenseActivationProperties` — POST/PATCH property rows.
- `EClientPropertyKey.Hostname` — `"hostname"`; WP always sends this on activate/sync.

## Non-obvious constraints

- These routes are **legacy contract style**; new reclaim endpoint in this package (or in api-real-commerce) should follow `createContract` per monorepo rule for **new** routes — do not copy `routeLocation*` for new work.
- Some files export `create…Contract` stubs that are intentionally `undefined` at runtime — use them only for type-level `ContractFromLegacyRoute` wiring, not as executable factories.
- POST request allows optional `licenseKey` (free-product mint path) but WP paid activate always sends a key.
- GET uses **params** for licenseKey/clientUuid; DELETE uses **body** nested under `licenseActivation` — keep that asymmetry when adding clients.
- Entity interfaces here are shared with TypeORM entities implementing them in the backend; keep enum string values stable.

## Known pitfalls

- Regenerating or dual-maintaining Zod contracts alongside legacy `IRoute*` without updating the PHP WP client will desync silently (PHP is hand-shaped JSON).
- Treating `ContractFromLegacyRoute` stubs as real `createContract` factories will break controller registration.
- Hostname is a client **property**, not a first-class column on `ILicenseActivation` — reclaim “find by hostname” must query properties / activations, not a top-level field on the interface.

## Pointers

- Sibling: `./backends-real-product-manager--license-activation.md`
- Sibling: `./api-real-commerce--contract-first-http.md` (pattern for any **new** HTTP contract)
- Skill: `.claude/skills/contract-first-http/SKILL.md`
