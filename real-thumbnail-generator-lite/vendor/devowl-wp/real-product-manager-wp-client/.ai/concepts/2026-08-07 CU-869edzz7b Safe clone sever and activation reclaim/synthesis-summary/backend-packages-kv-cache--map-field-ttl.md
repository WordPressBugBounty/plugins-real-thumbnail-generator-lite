# backend-packages/kv-cache — map field TTL + singleFlightCompute

## Source paths

- `backend-packages/kv-cache/src/types/cache/interfaces/cache-map-adapter.interface.ts`
- `backend-packages/kv-cache/src/types/cache/interfaces/cache-adapter.interface.ts`
- `backend-packages/kv-cache/src/common/strategies/single-flight-compute.strategy.ts`
- `backend-packages/kv-cache/src/cache/adapter/redis/field-ttl.helper.ts`
- `backend-packages/kv-cache/src/cache/adapter/node/field-ttl-sidecar.helper.ts`

## Responsibility

Provides the cache adapter surface for hash/map operations with optional **per-field TTL** (`fieldTtls` on `mapSet`) and stampede-safe cache-aside via `singleFlightCompute` (optimistic read → lock → re-read → compute → optional write / fallback suppress). Does **not** own domain keys (e.g. licenseKey→subscriptionId); callers choose key/field layout and TTLs.

## Public surface

- `mapSet(key, object, ttl?, fieldTtls?)` — write hash fields; optional whole-key TTL plus per-field TTLs; `0` on a field clears that field’s expiry.
- `mapGet(key, fieldNames)` / `mapGetAll` / `mapDelete(key, fieldNames?)` / `mapExist` / `mapGetTtlOfOneField`.
- `singleFlightCompute(keys, read, compute, options?)` — returns `{ value, source: "cache" | "computed" | "fallback" }`.
- `ISingleFlightComputeOptions` — `readAfterLock`, `write`, `fallback` (`run` + `ttlSeconds`), `fallbackToComputeOnLockError`, `locking`.
- `CacheFactory.createRedisAdapter` / `createNodeAdapter` — production Redis vs in-process node-cache (field TTL via Redis HEXPIRE vs sidecar map).

## Data flow

1. Caller `mapGet` selected fields; miss/undefined → expensive resolve (e.g. DB).
2. Prefer `singleFlightCompute([lockIdentity], read, compute, { write, fallback? })` so concurrent missers share one compute.
3. `write` typically `mapSet(hashKey, { [licenseKey]: subscriptionId }, wholeKeyTtl?, { [licenseKey]: fieldTtl })`.
4. Negative cache: store a sentinel or use `fallback` suppress marker (`ttlSeconds`) so provider failures do not stampede; suppress is a separate internal hash keyed by hashed identity.
5. Invalidate one membership: `mapDelete(hashKey, [field])` or `mapSet` overwrite after successful reclaim/activate so the next read re-queries the source of truth.

## Key types / contracts

- `ICacheTtl` — duration / absolute / fixed semantics consumed by `calculateTtl` (field and key level).
- `ICacheObject<ValueType>` — wrapper returned by mapGet/mapGetAll (`value` payload).
- `IMapCacheAdapter` / `ICacheAdapter` — full adapter contracts.
- `ISingleFlightResult<TValue>` — `source` discriminator for tests and metrics.
- Redis planner `planRedisMapSetFieldBatches` — groups HSET vs HSETEX (FNX/FXX KEEPTTL) + HPERSIST for field TTL `0`.

## Non-obvious constraints

- Lock identity (`keys` passed to `singleFlightCompute` / `acquireLock`) is **separate** from the cache key; monorepo guidance: for simple get/set pass `[cacheKey]` without a `:single-flight` suffix; for sharded `mapGet`/`mapSet` use a distinct lock key when fields share a hash.
- Omit `readAfterLock` when identical to `read` (default).
- `fallback.run` result is **not** written to the main cache; only a short suppress marker is stored.
- Node adapter emulates per-field TTL with a sidecar key (`__fieldTtl` suffix); Redis uses native field expiry — behaviour should match at the interface, not the storage shape.
- Whole-hash `ttl` and `fieldTtls` compose: omitted fields have no field-level expiry; hash TTL still applies if set.
- Default factory TTL is 60s when options omit ttl — set explicit TTLs for reclaim membership and negative entries.

## Known pitfalls

- Caching the **full reclaim decision** instead of membership (`licenseKey → subscriptionId`) couples TTL to business rules; framing wants membership-only + invalidate on successful activate/reclaim.
- Calling `mapGet` for serializer-sensitive numeric increments — interface notes `mapInc` / serializer caveats; prefer plain string/id fields for subscription ids.
- Forgetting field invalidation after a successful reclaim leaves a stale negative or wrong subscription id until TTL.
- Using process-local promise dedupe alone when multiple RPM/RC replicas exist — use `singleFlightCompute` + Redis adapter for cross-process stampede control.

## Pointers

- Concept (field TTL): `backend-packages/kv-cache/.ai/concepts/2026-06-18 CU-869drmwak per-field map TTL/`
- Concept (fallback): `backend-packages/kv-cache/.ai/concepts/2026-06-26 CU-869dvramm single-flight-fallback/`
- Tests: `backend-packages/kv-cache/test/vitest/unit/cache/adapter/redis/field-ttl.helper.test.ts`, `…/node/field-ttl-sidecar.helper.test.ts`
- Sibling: `./backends-real-commerce--license-subscription.md`
- Framing: `../domain-requirements/session-design-decisions-2026-08-07.md` (cache membership + short negative TTL + invalidate on success)
