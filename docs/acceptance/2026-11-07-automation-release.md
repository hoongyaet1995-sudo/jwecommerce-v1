# Automation / creative intelligence / resource-pool release

User explicitly approved synchronization in this conversation on7 November2026 (Asia/Kuala_Lumpur). Scope: locally accepted manual AI Post Automation, navigation, intelligence context, per-type source ranges and read-only preview. No timers, automatic publishing, demo catalog, sample pages, test Drafts, credentials, privacy/Reports/sidebar-redesign work or rebuilt unrelated assets included.

## Preflight
- Canonical local checkout branch codex/social-crm-identity at7890952aa49a29f87d9d5cd61cf7ceaba32d1384. GitHub main and Hostinger returned that exact SHA. Hostinger tracked files clean.
- Initial sandbox network checks could not access GitHub/SSH; authorized elevated read-only checks succeeded. No credentials printed or changed. Use existing jw-hostinger key profile and repository read-only deploy key.
- Required production tables marketing_post_automations and marketing_automation_runs are absent; free_post_generation_enabled is already true. This release requires only migration2026_11_06_120000_create_marketing_post_automations.php, whose up method creates those two new tables. No other pending migration is part of this release. User's approved synchronization includes the necessary additive schema for this feature; no existing business rows are imported, updated or deleted.
- Explicit file manifest/hash snapshot in local scratch automation-release-manifest.json; existing staged index must be empty before staging. Preserve unrelated working changes. No .env or local database/provider records staged.

## Accepted evidence
See2026-11-06-marketing-automation-manual.md,2026-11-07-automation-intelligence.md and2026-11-07-source-pool-integration.md. Final local feature acceptance:69 PHP tests402 assertions,15 JS tests, syntax/Blade, real desktop/mobile interactions and a prior real Draft. Known unrelated repository test failures remain documented; no claim of a fully green repository suite.

## Release gate
Recheck selected file hashes, exact staged paths, relevant tests and Blade before committing. Push accepted HEAD to GitHub main without force; deploy that exact SHA through approved application root, apply only the additive migration above, clear caches, compare local/GitHub/production SHA and perform read-only live HTTPS and authenticated-page checks where access permits. No paid AI call during release verification.

Execution outcome and final SHA are recorded in the local durable issue ledger / user handoff after deployment; this file is the pre-release scope approval record and does not by itself assert deployment.
