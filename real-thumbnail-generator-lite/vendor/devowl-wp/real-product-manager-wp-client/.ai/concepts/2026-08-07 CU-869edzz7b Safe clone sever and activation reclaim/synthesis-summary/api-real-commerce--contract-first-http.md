# api-packages/api-real-commerce — contract-first HTTP pattern

## Source paths

- `api-packages/api-real-commerce/src/route/checkout/vat-id-check/vat-id-check.post.ts`
- `backends/real-commerce/src/controller/1.0.0/checkout/vat-id-check/vat-id-check.post.ts`
- `api-packages/api-real-commerce/src/route/checkout/cart-abandonment-email-blacklist.ts`
- `.claude/skills/contract-first-http/SKILL.md`

## Responsibility

Documents how **new** Real Commerce HTTP endpoints are defined with `createContract` in `api-real-commerce` and implemented with `ContractController` in `backends/real-commerce`. This summary is the pattern reference for a future RPM↔RC reclaim (or RC-owned) HTTP API — not an inventory of all commerce routes. Does **not** cover legacy `routeLocation*` commerce endpoints still in the package.

## Public surface

- `createContractCheckoutVatIdCheckPost` — POST `/checkout/vat-id-check`; request `{ vatId }`; 200 `{ vatId, isValid }`.
- Inferred types — `CheckoutVatIdCheckPostContract` / `Request` / `Response` via `InferContract*`.
- `createContractCheckoutCartAbandonmentEmailBlacklistPost` — sibling with success schema + `NotFound` literal errors.
- Backend: `@Contract({ contract: createContract…, getResources })` + `class … extends ContractController<Contract, Resources>` with `respond()` / `createResponse` / `ServiceError`.

## Data flow

1. Define side-effect-free factory with package-local `z`, `versions: ["1.0.0"]`, `route.path` + `method`, `routeDetails`, `request` / `response` maps keyed by HTTP status.
2. Export factory + inferred contract/request/response types from the api package barrel.
3. Backend controller decorates with `@Contract`, injects resources (e.g. `waitForCache()`), implements `respond()`.
4. Validation / business failures throw `ServiceError` with status + error codes; success returns `this.createResponse(status, body)`.
5. Typed clients use `createFetchClient` against the same contract (exhaustive status branches) — see skill checklist.

## Key types / contracts

- `createContract` / `ContractResponse.fromSchema` / `ContractResponse.fromLiteralErrors` — from `@devowl-wp/api`.
- `InferContract`, `InferContractRequest`, `InferContractResponse`.
- `ContractController` + `@Contract` — from `@devowl-wp/backend`.
- `EHttpStatusCodeSuccess` / client/server error enums for response map keys and `ServiceError` status.
- Naming: `createContract<Resource><Verb>`, types `XxxContract` / `XxxRequest` / `XxxResponse`.

## Non-obvious constraints

- Always pass the **package-local** `z` into `createContract` (not a global zod import from elsewhere).
- Factories must be side-effect free; no ORM or I/O inside the api package.
- Prefer deriving bodies from `create…Schema` + `pick`/`omit` when an entity schema exists; vat-id-check inlines a small `z.object` because there is no VAT entity.
- Skill recommends `profiles: ["internal"]` for internal-only routes; these two checkout siblings currently omit `profiles` — set `profiles` explicitly when the reclaim/RC hop must not be public.
- Framing for this concept: RPM↔RC reclaim is **HTTP contract-first**, not NATS / `createBrokerContract`.
- New routes must not copy RPM license-activation’s legacy `IRoute*` style.

## Known pitfalls

- Mixing legacy `@Route` + `IRoute*` controllers with a Zod contract for the same path causes double registration / type drift.
- Forgetting to export the factory from the api package index leaves the backend unable to import a stable contract.
- Literal error responses need `ContractResponse.fromLiteralErrors` (or equivalent) so clients can narrow on `code`; throwing only untyped 500s loses that contract.
- `versions: ["1.0.0"]` must match how the backend mounts versioned routes (same string family as sibling controllers).

## Pointers

- Skill: `.claude/skills/contract-first-http/SKILL.md`
- Sibling: `./backends-real-commerce--license-subscription.md`
- Sibling: `./api-real-product-manager--license-activation.md` (legacy contrast)
- Framing: `../domain-requirements/session-design-decisions-2026-08-07.md` (HTTP not broker)
