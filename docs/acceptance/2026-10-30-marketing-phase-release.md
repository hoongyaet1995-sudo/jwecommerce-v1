# Marketing phase release

Approved 2026-10-30: Audience page-driven Import/Export, Email unsubscribe, Campaign/Template/Posts table actions. No unrelated storefront/inventory changes. No real send.

Ledger: release-isolated suite first run blocked by MissingAppKey (98 tests hit same environment cause; issue count 1). Added ephemeral testing-only APP_KEY to untracked release PHPUnit configuration. No production key copied or changed. Rerun after environment repair.

Release-isolated verification after test configuration repair: 104 tests / 680 assertions passed (16.928s); JS 10/10 passed. No DB migrations or provider calls. Prior normal localhost evidence retained in phase acceptance records.

## Live receipt (local evidence after release)
- Canonical localhost, release checkout, GitHub main and Hostinger HEAD: b7616349b436fea24a9be7029cfe0a5937d68f39.
- Hostinger ff-only pull and optimize:clear succeeded.
- HTTPS posts.js, campaigns.js, templates.js, templates.css and audience-transfers.js all 200 and byte-identical to committed blobs.
- Authenticated normal Chrome pages: Posts Edit/Delete, Campaign View/Duplicate, Template Edit/Duplicate/Archive/Delete verified. Read-only checks only; no deletion, copy, send or publishing performed online.
- Screenshots: output/posts-actions-live.png, output/template-actions-live.png (private local evidence, not committed).
- Existing hosting Audience worker cron remains configured but cannot advance page-driven work. Laravel Audience processing schedule removed; Posts/Email workers unchanged. No real Import/Export or unsubscribe executed online this release.

## Next convergence phase
Email first: segment eligibility and suppression, recipient snapshot, one confirmation, background delivery/progress, no resending unknown outcomes, delivery log and bounce feedback mapping. CRM source reuse permitted; no real bulk send until recipient scope is approved. Then WhatsApp approval/cost/receipts, SMS provider/cost, Audience scale/staff permission evidence. Birthday account UI remains deferred.
