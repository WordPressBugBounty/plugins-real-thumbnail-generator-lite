# CI runner clone sever test results — 2

Date: 2026-08-09 (evening) · Host: `wordpress.ci-runner-12.owlsrv.de` · License API: `license.ci-runner-12.owlsrv.de`  
After RPM restart: reclaim route live. WP client: plan 04 via monorepo vendor symlink.

Methods: WP-CLI in WordPress container + local RPM access logs. Browser not required for this round.

## Environment

| Item                   | Status                                              |
| ---------------------- | --------------------------------------------------- |
| Reclaim route          | Live (`POST …/reclaim` → business 422/200, not 404) |
| License keys on runner | **One** key only → V4 dual-key N/A                  |
| Multisite              | No                                                  |
| Site left              | Activated; hostname = runner host                   |

## Results matrix

| ID                      | Scenario                                           | Result     | Evidence                                                               |
| ----------------------- | -------------------------------------------------- | ---------- | ---------------------------------------------------------------------- |
| V2                      | Intentional same-host deactivate                   | **PASS**   | RPM `HTTP DELETE` for active UUID                                      |
| V4                      | Programmatic dual-key (MetaShare)                  | **N/A**    | No second key / programmatic filter                                    |
| V5                      | Reclaim success (resync, same key)                 | **PASS**   | Sever → reclaim `info` POST → local restored + `info` hint; no DELETE  |
| V6                      | Host check blocked                                 | **PASS**   | `rpm_wpc_host_check_blocked`; code retained                            |
| V7                      | `siteurl` update does not rewrite license hostname | **PASS**   | Option unchanged                                                       |
| V8                      | Toolkit-like CLI burst                             | **PASS**   | `cache flush` + `rewrite flush` exit 0                                 |
| V9                      | Sync/reclaim after sever (no prod DELETE)          | **PASS**   | Reclaim after sever; DELETE in window was V2 only                      |
| P0 Toolkit approx       | Foreign hostname + CLI + sever                     | **PASS**   | Exit 0; sever+reclaim restore; no DELETE of cloned UUID                |
| P1 First clone          | No RPM activation for hostname                     | **PASS**   | Sever → reclaim warn → warning hint; empty local; no sever-time DELETE |
| P1 Resync ×2            | Two successive foreign-host syncs                  | **PASS**   | Both restore host + `info` hint                                        |
| P1 Domain rename        | `siteurl`/`home` → other host then sever           | **PASS**   | Sever fires; reclaim fail-open (unknown host); siteurl restored after  |
| P2 Search-replace       | Plain host string replace in `wp_options`          | **PASS**   | Base64 `rpm-wpc-hostname_*` unchanged (3 other option hits reversed)   |
| P2 Empty host bootstrap | Empty persisted hostname + code                    | **PASS\*** | `sever=false`; bootstraps current host (**residual risk (1)**)         |
| P2 Host already match   | Staging host + foreign UUID                        | **PASS\*** | `sever=false` (**residual risk (4)** — remote still possible)          |
| P2 Multisite            | —                                                  | **N/A**    | Not multisite                                                          |
| P2 Temp/cloud host      | —                                                  | **N/A**    | Covered by V6 skip-flag only                                           |

\*Documented residual from incident follow-up — behaviour confirmed, not a regression of plan 04.

## Customer-path gaps still open

1. **Real Plesk/WP Toolkit clone** (full file+DB copy) — only approximated.
2. **Dual-key programmatic (MetaShare)** — needs second key + filter on runner or MetaShare staging.
3. **Fingerprint / fix for residual (1) and (4)** — still product/engineering follow-up.

## RPM log highlights (this run)

- Reclaim success: `info … POST /1.0.0/license/activation/reclaim` (e.g. 17:50:37, 17:51:11, 17:51:15, 17:52:23).
- Intentional DELETE only: 17:51:05 (first-clone **setup**), 17:52:16 (**V2**).
- No DELETE attributed to host-mismatch sever/reclaim paths.
