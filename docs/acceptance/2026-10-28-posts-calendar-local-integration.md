# Posts and Calendar — local integration checkpoint
Status: ready for local preparation-flow testing. External publishing remains unavailable. Not a live publishing acceptance or release.

## Approved scope implemented
- Approved Bootstrap UI and English copy ported to formal Posts/Calendar routes. Compact empty-state CTA and toggle styling retained.
- MarketingPost MySQL persistence, current-user ownership and merchant scoping; Marketing edit permission required for writes. Real local Channels records only, no provider identity probes.
- Shared record source for Posts and Calendar; undated/dated drafts remain Draft. Explicit schedule review, UTC timestamp + IANA timezone, version conflict rejection and immutable approved revision snapshot. Cancellation retains content/history.
- Per-destination outcomes. Test-only injectable fake adapter and unique revision/connection claims. Published targets retained; unknown submissions cannot edit/resend. No real publisher, queue worker, cron registration or web simulation endpoint.
- Real existing catalog images and current-user completed template videos; search/page library, scoped local file streaming, hash-verified video, no remote media downloads. No original file changes.
- Shared-host rendering-disabled gate permits preparation writes only; OAuth/callback untouched.

## Tests / evidence
- Initial intentional TDD red: missing Posts data endpoints, recorded separately in existing ledger.
- Latest `php artisan test --compact --filter='MarketingPostsTest|MarketingChannelsTest'`: 25 passed / 155 assertions. Includes owner/employee denial, foreign/missing account, stale version, future time/content/media checks, cancellation, immutable successful targets, fake permission/media/unknown results, no resend, dated draft != schedule, remote/traversal denial, fake-execution test-only gate. Http::preventStrayRequests + assertNothingSent throughout.
- `node --check public/marketing-studio/posts.js`: pass. Blade view cache: pass. Scoped diff whitespace check: pass.
- Only new Posts migration applied to canonical local MySQL; existing migrations/data not reset.
- Normal Chrome localhost: login restored by human; empty-account guidance; existing media search for Orbit; image:230 selected without generation; draft saved, reload/reopen retained; missing destination disables confirmation; Calendar date30Oct draft saves as Draft and appears in both views; canonical /calendar reload retains it; no console errors.
- Acceptance records retained: 9b1e1ced-4b8b-4a81-8d3b-1401efe4fc6d (`Local acceptance · draft only`) and45cc79d4-5152-4814-9c4c-33a7b960d645 (`Local acceptance · dated draft`). No fake connection rows inserted.
- Local connections before browser acceptance:0; no online provider/account/callback changes made. No paid API, publication, commit, push or deployment.

## Limits / next gate
Local DB has no connected social accounts. Real-account schedule confirmation, cancellation and failure UI against a locally connected account are not browser-accepted; fake backend tests cover these paths. Narrow-screen layout and full multi-tab/browser-back scenarios need additional UI regression before release. No general-customer platform readiness, actual cron dispatch, retries, upload, or publishing is claimed. Future publisher work must revalidate permissions/media and require explicit release/publication authorization; do not assume held schedules are executable.

## Issue ledger
Durable ledger: work/video-quality-review/merchant-readiness-20261027/ledger.md. Resolved: migration facade import1; explicit UTF-8 filewrite1; media catalog path compatibility2 diagnostic refinements(public/images and same-origin stored URLs), no remote fetching. No blind unchanged reruns. Elapsed/token totals unavailable.
