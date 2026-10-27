# Marketing navigation and Channels — local acceptance / synchronization scope

## Decision and status

Develop and fake-test on localhost. After explicit approval, synchronize this scoped phase to GitHub and Hostinger, then verify real Facebook/Instagram authorization on `https://ecom.jwaistudio.online`. Continue using the existing shared Social Hub and existing Meta callback whitelist. No development domain, tunnel or extra Hub client.

Baseline HEAD: `f7ee4b4e589f0b89368703678fdcef77aa2f798a`. Changes remain uncommitted. Existing unrelated dirty work is excluded. This is local acceptance, not a live OAuth completion claim.

## Proposed release scope

1. Previously approved full Marketing navigation and preview page skeletons: Overview, Campaigns, Posts, Calendar, Analytics, Content Library, Channels. Preserve real video projects, asset library and workspace links. New unimplemented modules remain explicit previews. Preserve shared-host rendering-disabled notice and backend execution locks.
2. Channels real Facebook/Instagram connection management: existing signed Social Hub launch, bound callback handoff, account picker, encrypted independent Marketing connection storage, explicit read-only Meta identity check, and local disconnect. Tokens never enter frontend DTOs. Do not invoke CRM messaging subscription, import legacy tokens or revoke other modules' credentials.
3. Security: owner-only mutations, marketing.view read-only access, CSRF and route throttling; actor/merchant/app/installation/transaction/state/expiry binding; public HTTPS and Marketing callback domain validation; one-time selection and sanitized failures. Actual returned scopes are distinct from enabled publication/analytics, which remain disabled.
4. One additive migration creates `marketing_channel_connections`. No existing table rewrite, seed data, production-token import or local media/data transfer.
5. Focused tests and release documentation/manifest. Exact 21 application/test source files and SHA-256 hashes: `docs/acceptance/2026-10-27-marketing-channels-source-manifest.json`.

## Local acceptance evidence

- Final focused suite covers Channels, independently signed FB/IG fake transport, existing Social Hub/CRM, navigation and shared-host mode. Result recorded in `work/video-quality-review/channels-meta-20261027/final-local-regression.txt` and phase ledger.
- PHP syntax, JS syntax and scoped diff whitespace checked. No Node/Admin bundle build is required: this scope uses Blade and directly served vanilla JS/CSS only. Existing unrelated generated Admin bundles are excluded.
- Normal local Channels page previously inspected through authenticated Chrome: actual empty connection state, original channel-card UI, planned TikTok/YouTube, and explicit domain-mismatch gate. localhost does not borrow the production registration to perform live OAuth.
- Fake fixtures use SQLite and synthetic credentials. Live Hub/Meta configuration and existing local installation settings remain unchanged.
- Resolved verification issues: missing nested social migration in the new test fixture, atomic encrypted disconnect write, truthful preview assertions, and missing shared-host notice. Keep history in existing ledger. The old expired paid-pilot issue remains paused and outside this scope.

## Explicit exclusions

No Social Hub source/deployment/settings changes; no Meta callback edits, app mode/scope changes or app-review submissions. No posts, publication, scheduling, analytics collection, messaging subscriptions, paid provider calls, automatic retries, render-tool installation or video generation. No catalog, theme, inventory, unrelated Admin source/bundles or .env release.

## Approval-gated synchronization and live verification

1. After approval only, build a clean scoped release from the baseline plus the exact accepted files; include this acceptance record. Do not publish unrelated dirty work. Run checks against that release artifact.
2. Back up production DB, deploy the same committed SHA, run only the new additive Channels migration and clear application caches. Keep execution disabled and existing Hub/app configuration unchanged. Verify local release snapshot, GitHub main and Hostinger SHA match.
3. Using Joshua Chrome profile, log into production Ecommerce and run Facebook then Instagram from Channels. Confirm redirect to the existing Social Hub, provider approval, return to the same production browser session, asset selection and encrypted persistence. User completes any provider permission approval; do not approve scope expansion silently.
4. Explicit identity GET checks may verify selected accounts. Do not click legacy CRM select/check flows that activate messaging. Read-only identity is not proof of publication/analytics rights.
5. Verify production projects/navigation remain accessible, unrelated CRM connections unchanged, and shared-host generation remains blocked. Stop and report any auth/scope/config blocker without rewriting Hub/callbacks.
6. Record exact live outcomes. Until these steps pass, real authorization remains pending. Preserve connection records if rolling back application code; do not drop the new table after live tokens have been saved.
