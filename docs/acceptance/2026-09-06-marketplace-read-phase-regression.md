# Marketplace local read-phase consolidated regression

Date: 2026-09-06. Local worktree only; no commit, push, deployment, cron installation or provider write performed by this regression.

## Fresh execution results

| Check | Result |
| --- | --- |
| PHP artisan test filter Marketplace or Shopee | 75 passed, 489 assertions |
| Disposable MySQL selected constraints/import/order suite | 11 passed, 142 assertions |
| MySQL independent-process order snapshot race | PASS; same ID, one row, no status regression, encrypted payload |
| MySQL independent-process same-store mapping race | PASS; one mapping, one 409, exact audit count, cross-store reuse, unchanged stock |
| Frontend marketplaceOperations and OrderInspectionDrawer tests | 9 passed |
| Admin TypeScript noEmit | PASS |
| Isolated order preview Vite build | PASS; order-Da8UAeSJ.js and order-C3U2LsF9.css |

All commands exited 0. Each temporary MySQL schema was removed after its run. The database queue tests use synthetic jobs and do not call providers. Browser fixture screenshots from the prior UI acceptance are not a substitute for authenticated real-order rendering.

## Candidate release boundaries

Enable only the new read flag for the verified Shopee Sandbox deployment, keeping general operations disabled. Scope includes empty-catalog compatibility, protected catalog/order reads and runs, real-data order detail contract, and a bounded dedicated queue command. Import improvements (gallery, safe description, brand) remain dormant behind the general operations gate; their tests do not authorize enabling that gate. The order drawer remains a local-only isolated preview; do not switch the official OMS route.

## Required next gate

User phase synchronization approval is still pending. After approval: stage explicit Marketplace source/test/build files only (many unrelated untracked artifacts exist), push the accepted commit, deploy that exact SHA, verify configuration and the intended Sandbox store, inspect pending jobs, then perform controlled real catalog/order ingestion and inspect persisted results. Do not copy secrets or tokens into Git, drain unrelated queues, enable real publishing/stock writes, or treat a queue submission as completed ingestion.

## Full-goal work still open

Real database/browser ingestion acceptance; operational ZIP30 cutover; unattended queue installation and recovery; complete structured options/variant images/category review; controlled real import/publication; order fulfillment and stock events; webhooks; remaining providers and shared AI engine. This regression is not overall Marketplace completion.
