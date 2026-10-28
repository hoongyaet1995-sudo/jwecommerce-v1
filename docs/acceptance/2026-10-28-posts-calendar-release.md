# Posts / Calendar release and first image publishing
User approved sync and real image publishing tests to Facebook JW Studio and Instagram hoongyaet95 (test accounts), keep posts. Scope: owned DB drafts/calendar, native toggles/compact CTA, immediate owner-confirmed JPEG image posts only. Future schedules held: no cron or automatic scheduled dispatch. Real publisher default-off behind MARKETING_SOCIAL_PUBLISHING_ENABLED; preparation works without rendering runtime. TikTok/YouTube remain draft-only.

Local fake regression: 29 tests / 174 assertions; no real HTTP. Atomic per-destination claim before submission, container ID persisted before Instagram publish; timeout/unknown no resend; known-container readiness checks bounded. Signed30minute delivery image: local deterministic JPEG with white padding preserving original. No paid generation/tool installation. Owner-only immediate action; normal edit permission for drafts. Hub OAuth/callback/config untouched.

Source: canonical C:/xampp/htdocs/jwstudio-ecommerce, clean scoped release mirror; all other dirty work excluded. Release review removed duplicate route declarations; regression repeated with new evidence. PHP8.2 Hostinger GD/JPEG already available. Production root verified /home/u762348670/apps/jwecommerce. Initial production HEAD b87e21d84faf24f6e42f8c478244a07dde8ec23f; five connection record fingerprint5c5a43c5bb78bedeceef454dacdccf2f65ceb21fa0a6370932960a13ad0b3ed5.

Planned normal-page test caption: JW Marketing Studio publishing test. This is a test post; no offer or product claim. Use existing catalog image, clearly test-only. Final real receipts and same-SHA/live evidence recorded in work/video-quality-review/merchant-readiness-20261027/ledger.md. No claim of success before actual provider receipts and online checks.

References: Meta Instagram API official Postman collection https://www.postman.com/meta/instagram/documentation/6yqw8pt/instagram-api ; Meta Page Photos API https://developers.facebook.com/docs/graph-api/reference/page/photos/ . Official docs direct fetch rate-limited during research; no third-party implementation copied.


## Real publishing acceptance
Normal production page, one approval of post349477b4-4159-4ffd-a6b2-02f995503d12, image from existing20ft General Purpose Container catalog item (Jasmine is local-only, not found in online media). Test-only caption exactly as above. Facebook Page JW Studio receipt1045679445296291_122132392268949900; Instagram hoongyaet95 receipt18124757123474289 and known container18381905326228607. Both completed, exactly two destination attempts, no retry. Read-only Graph verification returned200 for each and matching caption.
Facebook: https://www.facebook.com/122103733838949900/posts/122132392268949900
Instagram: https://www.instagram.com/p/DfB8xjME4-P/
Originals retained and test posts kept as authorized. Future scheduled dispatch and video publishing remain unavailable. Follow-up corrects stale disabled copy in preview/delivery UI and records actual immediate approval timestamp instead of the internal future-time preparation placeholder; no repost.

Release environment test setup: isolated checkout has no real .env; ephemeral test-only APP_KEY and APP_URL127.0.0.1:8500 supplied to the process, SQLite memory only. Corrected environment then passed29tests174assertions with release-local autoload. No production secrets copied. SSH reached via key profile; actual root /home/u762348670/apps/jwecommerce verified, short /apps path absent. No unrelated dirty source or server files overwritten.
