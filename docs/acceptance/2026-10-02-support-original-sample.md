# Original Support source sample

User direction: supersedes the simplified standalone design. Copy the original Support UI first and use the copy for convergence; preserve both dark and light modes. No API or LLM. No official-route cutover.

Baseline: branch main, HEAD 6d21a19. Existing inventory-related dirty files and earlier standalone Support sample preserved. Scope: new src/previews/supportOriginal, separate Vite config/output, source-copy helper and this record. Source component hashes recorded in source-baseline.json after copy.

## Durable issue ledger

- 2026-10-02 18:15 MYT, Playwright CLI open /admin/support: redirected to /admin/login. Authentication blocker; affected live-original comparison paused immediately. No credential guessing. Local source inspection continues. No visual-parity claim against the authenticated page.
- 18:17 MYT, node tmp/create-support-original-sample.mjs: source-copy helper failed before writing files due to nested template literal escaping. Issue O1 cumulative unexpected failures 1. Next step: replace nested template literal with ordinary string concatenation; rerun with that concrete correction.
- O1 repaired by string concatenation; helper succeeded. Original Support, header/sidebar and toast components copied byte for byte. O1 resolved; count remains 1.
- 18:18 MYT: `npx vite build --config vite.support-original-sample.config.ts` in resources/admin passed (1685 modules). Output isolated to public/support-original-sample; existing build-admin untouched.
- Next run: Playwright CLI open /support-original-sample/, inspect render, theme switching, demo text reply and context toggle. Evidence: successful isolated build. Separate context has no production bootstrap/provider, API or LLM imports; original support handlers use sample-only storage keys.

Elapsed time and token usage: unavailable. Unrelated historical accounting not reconstructed.

- Source hash check: all nine copied files match the original bytes, including four Support components, header/sidebar, toast renderer, Support types and fixture data.
- First browser checks: dark/light root class switches passed. Screenshot review prompted waiting for transitions before final screenshots.
- Issue O2: verification expected header background in rgba notation; browser wait timed out (30 seconds). Cumulative failures 1. Next: inspect actual computed color before replacing the fragile assertion; no UI changes based on this timeout alone.
- O2 diagnosis: browser returns CSS oklab for the light header, not rgba. Replaced format-dependent wait with theme class checks and screenshot animations disabled. O2 resolved; count remains 1.
- Final browser run passed: light/dark switching and theme persistence, context toggle, local text reply, resolve/reopen. No fetch/XHR requests observed during tested actions and reload. Desktop light/dark and mobile dark screenshots saved under output/playwright/support-original-sample-*.png.

This is the original source baseline, not a simplified candidate. Existing original layout/copy/fixture inconsistencies remain for later convergence. The isolated local context preserves original dummy support handlers; AI draft controls simulate locally and do not call an LLM. Other Admin navigation/actions remain disconnected. Original authenticated-page visual comparison remains pending due to login redirect. No application API, database, push or deployment changes.

## Convergence round 1

User authorized convergence on the original copy. Scope: preserve original light/dark styling; pending/resolved filters and unread dot; text reply and resolve/reopen; collapsible customer contacts/private notes, closed initially. Hide channel filters, AI controls/drafts, voice/attachments, sales intelligence, metrics and business quick workflows. Fix nested workspace height so composer fits without ancestor scrolling. Original source hash manifest remains the baseline; preview files now intentionally diverge.

Before next build: `npx vite build --config vite.support-original-sample.config.ts`. Purpose: compile the preview changes only. New code: copied UI convergence and preview draft state. Before browser run: verify both themes, collapsed panel, filters/search, per-thread drafts, reply, resolve/reopen, notes and mobile layout. Cumulative O1=1 resolved, O2=1 resolved; no reset or blind rerun.

- 18:26 MYT isolated Vite build passed, 1685 modules. Browser run next: Playwright CLI on original sample URL, desktop 1440×900 and mobile 390×844. New evidence: compiled convergence candidate; verify height, themes, draft isolation and core transitions. No migrations or backend tests needed for this demo-only artifact.
- First convergence browser run passed: collapsed context, text drafts across conversations, simulated send, resolve/reopen composer behavior, filters, empty search, notes and mobile navigation/no overflow. Visual inspection passed layout; follow-up polish: replace copied Dashboard breadcrumb with Support, label customer-details toggle clearly, remove floating preview badge that overlaps mobile composer helper text, select a matching conversation when switching Pending/Resolved. Next build/browser run verifies these concrete changes. No unexpected failures in round 1 so far.
- Final isolated build passed. Final browser check passed filter-to-selection consistency, both themes, mobile and desktop context, and no fetch/XHR requests during reload/actions. Final screenshots visually inspected at 1440×900 and 390×844. All nine original production files still match baseline SHA-256 values; preview changes intentionally differ. Round-1 unexpected failure count: 0.

Round-1 artifacts: output/playwright/support-convergence-{dark,light,desktop-context,mobile-list,mobile-chat,mobile-context,mobile-dark}.png. Sample URL: http://127.0.0.1:8500/support-original-sample/ . No official Support cutover, GitHub push or Hostinger deployment. Dummy fixture content remains illustrative and does not prove commerce/channel integration. User visual acceptance remains pending. Original baseline-copy helper must not be rerun over the converged preview because it would restore the baseline files.

## Standalone workspace request

User requested Support without Admin navigation/sidebar or global top info bar, plus return-to-previous-page button. Applied to isolated sample first; official route remains unchanged. Support fills the viewport. Theme buttons relocated into Support's existing header. Back uses same-origin referrer/history; direct entry falls back to /admin.

Before build/browser run: isolated Vite build, desktop/mobile dark/light screenshots, assertions for absent Admin shell, full-width workspace, visible composer, preserved theme changes and return navigation. New code: standalone wrapper and Support-header controls. Existing issue counts unchanged; no unexpected failure for this change yet. Elapsed time/token usage unavailable.

Result: isolated build passed (1681 modules). Browser assertions passed: no Admin header/sidebar, desktop workspace exactly 1440×900 from x=0, light/dark controls, mobile 390×844 without horizontal overflow or clipped composer, customer panel close, and same-origin history back returned to the actual preceding sample page. Screenshots visually inspected: support-standalone-dark.png and support-standalone-mobile-chat.png; light/list captures also saved. Unexpected failures for standalone request: 0. Direct-entry fallback targets /admin; authenticated destination acceptance remains outside this isolated sample. Official Admin navigation still awaits approved cutover.

## User-approved UI/UX baseline — 2026-10-02 18:35 MYT

User decision: “可以，就照着这个版本先把 UI/UX 定下来”. This approves the current standalone sample's UI/UX. Supersedes the earlier pending visual acceptance status for the sample only.

Frozen scope:

- Standalone full-viewport Support workspace, no Admin left navigation or global top information bar.
- Support header: return to previous page, Customer Support title, Light/Dark controls and customer-details toggle.
- Queue: search, Pending/Resolved filters, unread dot, existing source avatars/channel indicators marked as sample.
- Conversation: text history, separate drafts per conversation, sample reply, Resolve/Reopen; no replying while resolved.
- Customer contacts and private staff notes: collapsed by default; desktop side panel and mobile overlay.
- Hidden: AI drafting/handoff controls, channel filters, rich attachments/voice composer, customer sales analytics, order snapshot, voucher/invoice/export workflows.

Source and built preview SHA-256 hashes saved in `resources/admin/src/previews/supportOriginal/approved-ui-baseline.json` (12 files). Git baseline remains main at `6d21a19b30933b25ee8c1d72e03051c53e37ee88`, with existing dirty work preserved. Future backend integration should preserve this approved visual/interaction scope; new design scope requires a further user decision.

Approval is for UI/UX only. Messages/notes/statuses remain dummy local interactions. API/LLM/storefront chat connection, official /admin/support cutover and authenticated acceptance, commit/push/Hostinger release are not complete or authorized by this freeze. No code/build changed for this recording; no redundant browser/test run. Measured elapsed time/token usage unavailable.

## Authorized localhost official-route cutover

User explicitly requested “接到正是页面吧”. Official localhost /admin/support cutover is now authorized; push/deploy remains unapproved. Baseline: target App/context and original Support component files clean before this change; earlier inventory/build dirty work preserved.

Changes: copy approved four Support UI components into canonical components/support; retain actual AdminProvider/session and existing dummy support data/actions; add per-conversation draft state. App hides global shell and removes spacing/width constraint only for Support, preserving page-editor standalone behavior. In-app Support navigation records previous Admin path in history state so Back returns to that page through popstate. Existing API-backed unrelated Admin initialization remains as before; no new chat API or LLM integration.

Before validation: focused existing Admin-route Vitest checks and production Vite build; then use existing authenticated Chrome localhost tab to verify official route, themes, standalone shell and navigation back to prior Admin page. Evidence: source integration complete; fresh authenticated tab discovered (earlier isolated CLI auth blocker no longer affects this browser). No passwords extracted or authentication bypassed. Elapsed/token usage unavailable. Existing O1/O2 resolved counts persist.

Cutover validation results:

- `npx vitest run src/integration/__tests__/adminRoutes.test.ts`: 6/6 passed.
- `npm run build`: passed, 2989 modules; measured build time 10.93 seconds. Existing large-chunk warning remains, no build error.
- Existing authenticated Chrome tab confirmed /admin/support shows the approved standalone UI with no Admin sidebar/header. Workspace spans full viewport from x=0. No Laravel auth/session bypass.
- Actual Commerce sidebar -> Customer Support -> Return button returned to /admin/commerce, restoring normal Admin sidebar/header. Previous route retained through SPA history state.
- Official light/dark controls work. Draft restored after selecting another conversation; test draft cleared without sending. Existing demo state and actual signed-in provider retained.
- Official mobile 390×844: composer bottom=844, no horizontal document overflow; context panel opens/closes, Back to conversations returns to list. Browser viewport override reset after acceptance.
- Screenshot evidence: `output/playwright/support-official-{light,dark,mobile}.png`. Cutover unexpected failures: 0. Total task elapsed/token usage unavailable; prior resolved issue counts unchanged.

Accepted official source now lives in `resources/admin/src/components/support/`, with shell handling in App.tsx and draft/return-history state in AdminContext.tsx. Independent approved sample remains available. Local build-admin regenerated from the combined working tree, retaining earlier inventory work. No Git commit/push/Hostinger deployment or chat API/LLM connection performed. Approved localhost UI is now wired; production still has the previous version pending separate release approval.

## Admin shell side task

User requested removal of the sidebar-to-page gap and the sidebar brand-header collapse icon. Root cause: sidebar w-64 (256px) versus shell lg:pl-72 (288px). Fix: shell lg:pl-64; remove sidebar collapse button/import/destructured handler only. Keep page internal padding and existing top-header desktop toggle/mobile menu behavior. Standalone Support remains offset-free.

Before build/browser check: npm run build; verify authenticated /admin/commerce sidebar right equals header left and removed brand-header button; verify top-header collapse/reopen and Support standalone. Existing dirty source/build preserved. This side task has no unexpected failures yet. Elapsed time/token usage unavailable.

Result: build passed (10.05 seconds measured). Authenticated Commerce DOM measured sidebarRight=256 and headerLeft=256, gap=0; removed icon count=0. Top-header collapse moved header left to 0 and reopen restored navigation; transient measurements during reopen animation were not used as settled dimensions. Support remains standalone after sidebar navigation. Screenshot: output/playwright/admin-sidebar-gap-removed.png. Unexpected failures=0. Local only; no push/deployment.
