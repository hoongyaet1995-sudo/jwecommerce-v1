# Support image messaging checkpoint

Baseline: c224f2a20d543c172ab6d8a60d4bc396d4b58fb7, branch codex/social-crm-identity. Existing unrelated dirty checkout/inventory/docs/build deletions preserved. User approved design and implementation. No synchronization authorization for this phase.

Ruling: Implementation remains in canonical local root; no worktree/commit until requested sync, per project contract. Execute inline.

## Issue ledger
Historical accounting for unrelated issues unavailable; not reset. Elapsed time/token usage unavailable.

2026-10-06: Task 1/2 intentional initial TDD red planned: php artisan test --filter=SupportImageMessagingTest. Purpose: absent upload and image-message behavior. No product changes yet. Expected missing endpoints/model migration, recorded separately from unexpected failures.
`nInitial test blocked before product assertion: GD missing in CLI (unexpected environment issue count 1, paused). Verified installed php_gd.dll; use php -d extension=gd for this phase. Next intentional red now runs with GD loaded.
`nGD issue count 2: artisan test spawns child PHP and discards -d. Concrete diagnostic: invoke vendor/phpunit/phpunit/phpunit directly with -d extension=gd instead.
`nGD environment resolved by direct PHPUnit invocation. Initial product red confirmed missing upload endpoint 404. Implemented private upload/binding/serving; next run verifies Task1/2 green.
`nPatch script encoding issue interrupted migration-test setup (unexpected issue count 1 for partial patch). Changed Python UTF8 mode; completed missing edits before rerun. No blind rerun.
`nTask1/2 focused green: 3 tests 23 assertions. Task3 initial intentional red: new provider image send/receive tests before adapter edits.
`nTask3 adapter edits complete; Ruling: Facebook/Instagram image caption must be a separate text message; reject combined caption to avoid partial multi-call delivery. WhatsApp/Storefront allow caption. Provider download pins public DNS IP and restricts HTTPS CDN hosts, redirects disabled. Next run verifies four provider tests.
`nTask3 first green run exposed missing WhatsApp payload initialization (unexpected issue count 1). Corrected actual spaced try-block before rerun.
Task3 regression green: 119 tests 779 assertions. Task4 helper red confirmed missing module. Implemented Admin/Storefront image controls, clipboard, preview/remove and viewer; next tests/build validate.
Task4 JSX image insertion omitted closing brace; test transform and build failed, cumulative issue count 2. Corrected exact JSX boundary. Next focused test/build has new source.
Task4 focused frontend green 15 tests, production Admin build passed. Local migration applied. Enabled installed GD in local XAMPP php.ini and requested Apache restart; no production config change. Added pending-upload cleanup command and WebP-to-PNG normalization for WhatsApp compatibility.
Final read-only agent review found WebP compatibility (already normalized), missing abandoned cleanup (already added), and WhatsApp 1024-char caption limit. Add regression tests before caption fix. Cleanup test validates sent/fresh files retained; host rejection tests validate media origin gate.
WhatsApp caption red reproduced 200 instead of 422. Added pre-reservation controller validation and composer error; removed repeated statements caused by interrupted UTF8 script. Next regression includes expiry cleanup and host guards.
GD issue Attention count 3: browser/local HTTP upload returns 503; CLI has GD but running Apache did not reload. Paused uploads. Inspect startup logs and restart command output before any retest. File chooser separate known extension-permission blocker; do not retry; screenshot clipboard accepted input.
GD root cause: httpd -k restart targets absent Windows service; XAMPP is console-launched. Apache config Syntax OK. Replaced only verified XAMPP httpd processes and restarted hidden with canonical server root. This reloads local PHP extensions; no production changes.
Additional delivery review: reuploading same image under a new attachment UUID must not bypass unknown-send protection. Declared intentional red for same-image fingerprint guard; test before fix.
Fingerprint red confirmed 200 instead of 409. Guard now compares private SHA-256 so reupload cannot resend an unconfirmed image; different images stay independent. Storefront upload errors now separate from polling notice so remain visible. Image-only Inbox snippet displays Image. Downloader hides token-bearing connection exception URLs.
Next final regression after new fingerprint guard and review fixes: php vendor/phpunit/phpunit/phpunit --filter=SupportChatApiTest|SupportImageMessagingTest|MessengerReplyTest|WhatsAppSupportTest; purpose verify caption/expiry/download-host and text behavior. No unrelated suite repeats.

Final download verification: added private storage success and sanitized terminal failure coverage. Run: php vendor/phpunit/phpunit/phpunit --filter=MessengerReplyTest. New evidence: downloaded media job state transitions.

Download fixture issue count 1: temporary UploadedFile destroyed before reading. Fixed by retaining fixture instance; rerun same focused suite.

Local browser acceptance: customer clipboard paste -> preview -> image-only send -> Admin receipt -> staff clipboard image reply -> customer receipt PASS. No AI text response for media-only. Admin lightbox and light/dark image rendering PASS; both images decoded at 426x240. Screenshots: tmp/support-image-admin-dark.png, tmp/support-image-admin-light.png. File chooser browser test paused: Chrome extension file URL permission disabled; clipboard and endpoint validation verified. Download focused suite PASS 27 tests / 148 assertions; fixture issue resolved. Prior full backend PASS 123 tests / 807 assertions; frontend PASS 15 tests; production build PASS. Real Meta image delivery and TikTok remain pending, not claimed. No commit/push/deploy in this phase.
