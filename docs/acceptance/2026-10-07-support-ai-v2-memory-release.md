# Support AI V2-B release scope

User authorized synchronization on2026-10-07 after local acceptance. Base5aa7bceaf6a18c46f7aa43e3ac4877c508fde6e0. Scope: customer memory policy/extraction/queue/context, staff controls, Knowledge opt-in, Guardrails retention and cleanup.

Local evidence: docs/acceptance/2026-10-07-support-ai-v2-memory-local.md;91backend tests566assertions,2frontend tests, build passed, no introduced TypeScript diagnostics. Default off/30days preserved. Actual synthetic extraction and return-language evidence available locally; live channel memory acceptance remains pending.

Release steps: push this scoped commit HEAD:main; Hostinger ff-only pull; apply only2026_10_07_190000_create_support_customer_memories_table migration; optimize clear; queue restart; verify exactSHA and liveHTTPS/Admin. Add dedicated hPanel daily support:prune-customer-memories cron with flock because application scheduler cron targets a different application root. Preserve existing cron jobs and settings. No backfill, no provider sends, no production memory enablement.
