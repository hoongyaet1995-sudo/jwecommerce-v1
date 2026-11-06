# AI Create Post release candidate — 2026-11-06

Target: GitHub `main`, then **ecom.jwaistudio.online** at `/home/u762348670/apps/jwecommerce`. This release does not target the separate www application.

Base local/GitHub/ecom SHA: `3e855e63b65642d7d30463113f13b04608a35570`. Source branch: `codex/social-crm-identity`. Exact scoped files and local pre-stage hashes: `2026-11-06-marketing-release-files.json`; text line endings may normalize in Git. Unrelated dirty changes, private files, `.env`, local drafts and cancelled Insights features are excluded. Existing template Canvas is not released.

## Verified locally

- 48 mocked PHP tests, 245 assertions, 22.21s: `php artisan test --compact --filter='MarketingFreePostTest|MarketingDailyDraftTest|MarketingCaptionTest'`.
- 23 JS tests, 448.2216ms: post-title, posts-v2, free-live, free-brief and reference-library Node tests. Formal Blade entry is tested instead of the retired preview index.
- PHP/JS syntax and staged diff whitespace checks passed. Direct public CSS/JS/PNG assets require no Vite rebuild. Unrelated existing build artifacts are excluded.
- Desktop and actual 390x844 DOM/interaction checks: header, product search and multi-select, single/multiple choice, refresh/back/cancel persistence, platform icons versus actual account linkage. Webpage pixel screenshot remains unverified due prior screenshot failures; 110 reference PNGs were inspected in their original generation phases.
- Independent review found ignored image-storage failure. Fixed: require successful write, file existence and SHA256 match before advancing. Mocked failure retains usage receipt and stops further provider requests. Review then found no remaining code blockers in scope.

## Read-only production preflight

- Production APP_URL is correct; base SHA matches; tracked checkout clean. Existing untracked backups/assets/worker locks were not altered.
- Existing OpenAI connection is enabled/verified, with GPT-5.6 Luna and GPT Image 2.5 Flare available. No provider requests made for this release.
- Connected channels: Facebook 1, Instagram 1, TikTok 1, YouTube 2. This does not auto-select a publishing destination for a draft.
- PHP GD and cURL available. Global Marketing execution is disabled; existing social publishing flag enabled. This release must not enable global execution, schedules, or publish posts.
- `marketing_daily_draft_runs` and its migration record are absent.

## Additional production actions pending explicit approval

1. Run only `2026_11_05_230000_create_marketing_daily_draft_runs.php` up migration: create a new private run table (UUID, user foreign key, merchant key, status, JSON snapshots/creative/receipts/post IDs, timestamps/index). Do not alter/backfill/delete existing business data. Do not run all pending migrations or rollback.
2. Set only `MARKETING_FREE_POST_GENERATION_ENABLED=true` for the ecom application. Default is false in production; local/tests remain available. Existing EDIT and APPROVE_COST permissions remain enforced; CSRF remains enforced. The exception to global execution is limited to create/step routes.
3. Users can then deliberately generate drafts through existing AI Connect at provider usage cost. This is not Marketing credit settlement implementation; no claim of wallet debit is made. No automatic schedule, generation, social account authorization or publication is introduced.

After approval, deploy the exact pushed commit, run only the approved setup, clear caches and verify live SHA, auth redirects, static reference assets and read-only readiness. Authenticated user generation/linking and production provider behavior remain live acceptance, not passed results. Local draft records and credentials must not be copied to production.
