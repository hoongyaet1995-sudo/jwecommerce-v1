# JTG CMS and AI context release - 2026-10-17

Explicit user approval: synchronize current phase including all new FAQ and JTG-facing information.
Baseline local/GitHub/Hostinger a49643a7c2a83380dc48bc87b852d988a7f54b0a. Dirty inventory, fashion and unrelated UI preserved.
Scope: JTG detail/gallery layouts,18FAQ, enquiry intake/current public+private context, provider recovery, admin controls, schema and targeted showcase importer. No credentials/customer/order data transferred. Export includes17JTG products,8categories,6collections, homepage/shell/menu/branding and3detail pages; importer preserves operational records and existing stock.
Ledger: clean scoped build and test, import check/verify, backup, exact-SHA deploy and live HTTPS checks pending. No blind retries. Elapsed/token usage unavailable.

- Scoped release tree: PHP200tests1324assertionsPASS24.77s; Admin production buildPASS12.52s; TS4testsPASS4.42s. Complete targeted import dry-run transaction verified/rolled back and existing operational records/stock hashes unchanged. Production support worker detected running from existing Hostinger cron with source-specific support-ai queue.
- Next: stage only clean-tree generated build assets, commit/push approved scope, pull exact main SHA, apply two named intake migrations, import backup+JTG data, clear cache and gracefully restart workers, public HTTPS/browser acceptance. No unexpected test failures.
