# Smoke — RPM activation reclaim (plan 02 T6)

Requires plan 01 same-subscription reachable and
`REAL_PRODUCT_MANAGER_REAL_COMMERCE_SUPER_ADMIN_KEY` equal to RC's
`REAL_COMMERCE_SUPER_ADMIN_KEY`.

Base URL: `https://license.<host>/1.0.0/license/activation/reclaim`
Body (example shape):

```json
{
    "licenseKey": "<proof uuid>",
    "clientUuid": "<proof client uuid>",
    "hostname": "<staging hostname>",
    "product": { "id": "<product id>" },
    "productVariant": { "id": "<variant id>" }
}
```

## Checklist

- [ ] Flag unset/true + valid proof + hostname activation → `200` with staging activation (key may differ)
- [ ] `REAL_PRODUCT_MANAGER_LICENSE_ACTIVATION_RECLAIM_ENABLED=false` → `503 LicenseActivationReclaimDisabled`
- [ ] Unknown hostname → `422 LicenseActivationReclaimNotFound`
- [ ] Wrong proof key/uuid (different subscription) → `422 LicenseActivationReclaimProofFailed`
- [ ] Prod activation untouched (no DELETE)

## Fixture recreation

Prod+staging keys under the **same** Real Commerce subscription; one Activated activation for the staging hostname on the product/variant; proof client UUID previously tied to any activation under that subscription.
Do not commit stack-local UUIDs.
