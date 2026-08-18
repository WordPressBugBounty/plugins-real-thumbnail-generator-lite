# Smoke — RC same-subscription (plan 01 T4)

Manual checks against a running local stack. Auth uses the existing
`REAL_COMMERCE_SUPER_ADMIN_KEY` (already under `/backends/real-commerce`).

Base URL (local): `https://real-commerce.<host>/1.0.0/license/same-subscription`
Header: `x-authentication-super-admin: <REAL_COMMERCE_SUPER_ADMIN_KEY>`
Body: `{ "licenseKeyA": "<uuid>", "licenseKeyB": "<uuid>" }`

## Checklist

- [ ] Wrong / missing secret → `401`
- [ ] Two keys from the **same** subscription fixture → `200 { "sameSubscription": true }`
- [ ] Two keys from **different** subscriptions → `200 { "sameSubscription": false }`
- [ ] Unknown key (valid UUID, not in DB) → `200 { "sameSubscription": false }`
- [ ] Malformed UUID → `422`

## Fixture recreation (no stack-local IDs)

Recreate with: any Customer Center / paddle sandbox subscription that has **≥2** license rows
on the same `subscription` (or one known key + invent a random UUID for the unknown case).
Prefer keys you can look up via existing license admin tools; do not commit concrete UUIDs.

## Vitest note

`backends/real-commerce` and `api-packages/api-real-commerce` have no Vitest workspace entry yet.
Behaviour coverage for this slice is this smoke checklist; unit/Vitest scaffolding belongs with a
broader RC test harness, not a one-off for this route.
