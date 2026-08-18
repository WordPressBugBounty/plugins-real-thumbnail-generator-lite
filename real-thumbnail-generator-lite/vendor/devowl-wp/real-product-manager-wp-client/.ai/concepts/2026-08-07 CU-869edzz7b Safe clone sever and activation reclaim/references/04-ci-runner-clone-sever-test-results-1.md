# CI runner clone sever test results — 1

Date: 2026-08-09 · Host: `wordpress.ci-runner-12.owlsrv.de` · License API: `license.ci-runner-12.owlsrv.de`  
WP client: monorepo symlink (plan 04 symbols present: `clearLocalLicenseIdentity`, `afterSeverBestEffortRestore`).  
RCB plugin header version: 5.2.31 (vendor package live from workspace).

Automation: Cursor browser MCP (login/UI) + `wp` in `devowl-wp_wordpress` container + local RPM container logs (not production New Relic — license host is local).

## Environment blockers

| Item                                               | Status                                                                  |
| -------------------------------------------------- | ----------------------------------------------------------------------- |
| Plan 04 WP client code on runner                   | Present (vendor → `wordpress-packages/…`)                               |
| RPM `POST …/license/activation/reclaim`            | **Missing** — responses `Cannot POST /1.0.0/license/activation/reclaim` |
| Second subscription key / programmatic filter (V4) | Not configured on this instance                                         |
| Production NRQL for this host                      | Empty for `ci-runner-12` UA (expected; traffic hits local license)      |

## Variant outcomes

| ID  | Result | Evidence (short)                                                                                                                           |
| --- | ------ | ------------------------------------------------------------------------------------------------------------------------------------------ |
| V0  | pass   | Basic Auth + WP login → `/wp-admin/`; Cookies menu present                                                                                 |
| V1  | pass   | License options stable across loads; POST activate + GET status on local RPM                                                               |
| V2  | pass   | `deactivate(true)` → local RPM `HTTP DELETE /1.0.0/license/activation` for active uuid                                                     |
| V3  | pass   | Host mismatch → `probablySever…===true`; local clear; reclaim attempted then fail-open; **no** DELETE for cloned uuid; warning hint        |
| V4  | n/a    | No programmatic dual-key filter / second key on runner                                                                                     |
| V5  | fail   | Reclaim called after sever but RPM route not deployed (`Cannot POST …/reclaim`); identity left cleared + warning (fail-open), not restored |
| V6  | pass   | `RPM_WP_CLIENT_SKIP_DYNAMIC_HOST_CHECK` → `rpm_wpc_host_check_blocked`; identity retained                                                  |
| V7  | pass   | `wp option update siteurl` (unchanged URL) did not rewrite `rpm-wpc-hostname_*`                                                            |
| V8  | pass   | With foreign hostname set: `wp cache flush`, `rewrite flush`, `option get siteurl` all exit 0 (no segfault)                                |
| V9  | pass   | After sever: reclaim fail-open only; no DELETE of cloned uuid in RPM logs                                                                  |

## Notable observations

1. Plain `wp cache flush` with mismatched hostname did **not** by itself call sever (options unchanged until `probablySeverClonedLicenseIdentity()` was invoked). Toolkit-style risk is “no segfault when/if sever runs”, which held on explicit sever + CLI boot.
2. Admin dashboard still showed a stale license notice referencing the simulated foreign host after restore until refresh cycles caught up; final restore re-activated successfully (`RESTORE_OK`).
3. Full V5 needs reclaim route live on `license.ci-runner-12` (plan 02 backend deploy).
