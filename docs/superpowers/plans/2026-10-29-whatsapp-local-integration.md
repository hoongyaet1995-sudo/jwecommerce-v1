# Approved local integration implementation
Canonical root: C:/xampp/htdocs/jwstudio-ecommerce. No release or live OAuth/send in this execution.
1. Add marketing_content_templates table/model: merchant/user ownership, channel, account/WABA, name/language/category, document, provider state/id, optimistic version. No old records overwritten.
2. MarketingContentTemplates service + protected controller CRUD; draft saving never submits; explicit status sync and explicit submission with default-off feature gate; pending/unknown states never Approved locally. Provider errors sanitized and no retry.
3. Shared Templates screen under expandable Campaigns; source-faithful shell + approved WhatsApp chat preview; four type chooser; Email/SMS drafts, Post saved templates open existing Posts editor as new Draft. Existing video templates route preserved.
4. WhatsApp campaign content records tied to shared Campaign: choose account/template, map values, save draft and readiness review. No synthetic audiences or prices. Missing opt-in source/cost must block confirmation; delivery worker remains off until separately verified/approved.
5. Fake tests: ownership/permissions, stored version conflict, WABA association, invalid variables/URLs, provider error/pending/status sync, Post reuse creates only Draft/no queue, readiness blockers. Test run ledger in WhatsApp acceptance checklist.
6. Run migration only new file locally; normal local browser save/reload/filter/preview; current Channels regression. Real OAuth requires matching approved production domain after future explicit sync approval.
