# Campaign and Template list actions — local phase

Approved request: reuse CRM table styling and menus. Campaign: View and Duplicate. Template: Edit, Duplicate, Archive/Restore and Delete. No publishing or provider calls.

Duplicate copies campaign configuration to a new draft, never posts, schedules, delivery records or results. Template Delete is a local logical deletion; submitted/provider-linked and referenced templates must use Archive. Merchant/owner, permission and version checks stay enforced. Existing unrelated changes retained.

Verification ledger
- Initial verification: focused Campaign/Template regression suites, PHP/JS syntax. Purpose: verify new actions and existing guards; no prior failures for this issue. Result pending.

- Verification: first two runs 26 pass / 1 fail: missing safe route name (423). Second repair script stopped on Windows decoding before middleware change. Issue count 2; fixed with explicit UTF-8, named route and narrow draft-only safe-list entry.

- Final focused run: 27 tests / 147 assertions passed, 4.32 seconds. Node syntax checks passed.
- Normal localhost browser: Campaign three-dot menu shows View Campaign and Duplicate Campaign. Template menu shows Edit, Duplicate, Archive and Delete. No existing template deleted and no campaign submitted/sent in browser. Screenshots: output/campaign-actions-local.png and output/template-actions-local.png.
- Not synchronized, committed, pushed or deployed. Token use unavailable.

## Posts follow-up
- User approved same CRM table and three-dot menu, Edit Post / Delete Post. Delivery states use View Post to avoid implying published content is editable.
- Delete only untouched Draft/Cancelled with no attempts, delivery lock or outcomes. Local tombstone preserves original document/media; campaign association cleared. Owner/merchant/version and edit permission enforced.
- Verification command: focused Posts/Queue/Content suites. Purpose: deletion, protected delivery states and UI regression. No previous failures for this issue; result pending.

- Posts focused verification: 34 tests / 178 assertions passed (6.60s), Node/PHP syntax passed. No unexpected test failures.
- Normal Chrome localhost: unified table, Edit Post / red Delete Post menu visible; Delete confirmation opens and Keep post preserves all six originals. Screenshot output/posts-actions-local.png. IAB unavailable, Chrome fallback used. No real publishing/provider calls, commit/push/deploy.
