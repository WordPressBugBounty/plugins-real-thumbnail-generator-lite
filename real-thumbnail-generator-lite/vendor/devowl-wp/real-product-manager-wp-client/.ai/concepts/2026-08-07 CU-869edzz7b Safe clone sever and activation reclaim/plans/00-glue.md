---
status: ready
sub-project: glue
index: "00"
---

# Glue — execution order and end-to-end smoke

## Implementation order

1. **01 — rc-same-subscription** — Internal RC route + SuperAdmin auth. Unblocks RPM client.
2. **02 — rpm-activation-reclaim** — Needs 01 live (or mocked in unit tests; integration needs 01). Reclaim + flag + cache.
3. **03 — wp-client-sever-and-reclaim** — Hook removal + sever stash can land early for production safety; reclaim HTTP needs 02. Prefer shipping sever/hook with 03 even if reclaim 503s (fail-open).

Do not point RPM at RC until 01 route is in the target environment and RPM holds `REAL_COMMERCE_SUPER_ADMIN_KEY` (plan 02). After any compose `environment:` change: `dowl docker:purge && dowl docker:start`.

## Shared scaffolding

| Item                                                            | Owner                                       |
| --------------------------------------------------------------- | ------------------------------------------- |
| RC SuperAdmin key copy for RPM (`x-authentication-super-admin`) | 02 (RPM consumer config; RC already has it) |
| Reclaim feature flag                                            | 02                                          |
| `ILicenseActivation`-shaped reclaim response                    | 02 contract (WP persist in 03)              |
| No NATS / no new shared package                                 | all                                         |

## End-to-end smoke (after 01–03)

1. **Clone kill prevented:** DB with prod key+uuid+prod hostname option on staging host → admin load → no `DELETE` with prod uuid on RPM (NR or access logs); prod GET still 200.
2. **First staging activate:** manual or programmatic activate on staging → OK.
3. **Resync reclaim:** overwrite staging DB from prod again → admin load → reclaim restores staging activation (possibly different key) without paste; prod still valid.
4. **Kill switch:** set reclaim flag false → reclaim 503; WP remains usable (sever + programmatic/manual); no prod DELETE.
5. **Dual keys:** proof prod key + uuid, target staging key same subscription → 200 reclaim.

## Plans

- [01-rc-same-subscription.md](01-rc-same-subscription.md)
- [02-rpm-activation-reclaim.md](02-rpm-activation-reclaim.md)
- [03-wp-client-sever-and-reclaim.md](03-wp-client-sever-and-reclaim.md)
