# AI Post Automation — local manual-generation acceptance

Recorded 2026-11-06. Branch `codex/social-crm-identity`, HEAD `7890952aa49a29f87d9d5cd61cf7ceaba32d1384`; this phase is uncommitted/local. Existing unrelated dirty work preserved. No push, deployment, `.env` edit, scheduler or publication.

## Delivered

- `/admin/marketing/automation` → AI Post Generation → independent saved Automations.
- Create Blank plus eight editable presets: Product Spotlight, Promotion & Offers, Product Education, Buying Guide, Tips & Checklist, Relatable Moments, Brand Story, Case Study.
- Lazy keyword search and explicit multiple source selections: active Products/Categories/Collections, currently valid Vouchers, published CMS Pages. No arbitrary configured-source count cap. Expired/unavailable selections remain visible until explicitly removed; save rejects unavailable sources.
- Direction prompt, nine Post Types with purpose, single/multiple image plan, CTA public HTTPS URL, platforms/language/tone/theme, weekday/time preferences default Asia/Kuala_Lumpur. Timing is informational only.
- Save does not call AI. Save & Generate Post durably records source selection, invokes existing Luna writing and GPT Image artwork chain, saves real Drafts, and links to the existing review page. An explicit new-generation action retains failed history; no unknown-result automatic retry.
- Owner/merchant scope, settings version conflicts, source-change checks before paid steps, currently valid voucher revalidation, visible public CMS copy extraction, current dynamic Collection rules without mutating pivots. Verified voucher terms appended deterministically; general no-model-money guard retained.
- No Canvas, no connected-account auto-selection, no store price/discount mutation, no publication.

## Real acceptance

Automation: `58d0ba20-2269-48d9-80cc-a12964c17036` (JW Studio Owner).

Successful generation: `68277a2e-142d-4cf7-93ef-255524d44fee`.
Review: <http://127.0.0.1:8500/admin/marketing/daily-drafts/68277a2e-142d-4cf7-93ef-255524d44fee>
Draft: `d0156168-9931-43d3-aa05-d03cf4bca89a`, Facebook, status Draft, one real image, `targets=[]`, Automation provenance present.

AI selected products190/191 from the two-product pool. Real Luna copy contains paragraphs, confirmed DDR4/UDIMM/3200 MT/s facts and exclusions, plus supplied HTTPS CTA. Real `gpt-image-2.5-flare` produced a 1024×1280 artwork, original company Logo applied. Actual image pixels inspected with view_image: readable memory headline/product labels, matching specifications/CTA, no price or invented offer. Final PNG SHA256 `407ecea8c95846222b4c4b89d1319e3b40311d558df90b70351ce8c0fe60e906`.

Repeated completed step with Http::preventStrayRequests returned completed with **zero HTTP calls**. Real review controller rendered HTTP200 using the actual saved Draft. This is server rendering, not browser/pixel layout acceptance.

First acceptance run `8dd771fb-d046-4bc8-b9d5-97f724aa336f` stopped at writing validation, no image/Post. Original service discarded raw result; its exact rejection cannot be reconstructed (one readonly response retrieval404). It was never reset/retried. Added bounded durable raw-response/error diagnostics, regression-tested, then made the separate successful diagnostic run above. Original failure remains in history, not claimed resolved. Five total paid requests across both runs (2 selection,2 writing,1 image); reported provider tokens10089. Calculated uncached-standard estimate US$0.047852, not invoice or wallet-debit confirmation. Agent token usage/total elapsed time unavailable.

## Verification

- `php artisan test --compact --filter='MarketingAutomationManualTest|MarketingAutomationPageTest|MarketingFreePostTest|MarketingCaptionTest|MarketingDailyDraftTest'`: **65 passed,361 assertions,28.99s** (`tmp/marketing-reference-expansion/automation-final-all.log`). Covers owned/versioned settings, source search, unavailable IDs, scope drift, dynamic rules, CMS-only real Draft persistence with mocked provider, vouchers, unsupported selections, global execution off, single/multiple image behavior, existing flows, no duplicate calls/publication, legacy request digest, retained rejected output.
- `node --test tests/javascript/marketing-automation.test.cjs tests/javascript/marketing-free-live.test.cjs tests/javascript/marketing-free-brief.test.cjs tests/javascript/marketing-posts-v2.test.cjs tests/javascript/marketing-reference-library.test.cjs`: **20 passed**,2217ms. Includes actual jsdom FormData save locking, explicit source removal persistence, no preload, explicit new-generation pointer clearing; existing content/reference/Post flows.
- PHP syntax checks for new controller/services/models/migration and changed FreePosts/Provider; JS syntax; targeted `git diff --check`: passed. Authenticated Blade pages rendered in feature tests. No TypeScript change or Vite entry; no unrelated production bundle rebuilt.
- Targeted local migration `2026_11_06_120000_create_marketing_post_automations.php` only: DONE281.57ms, creates settings/run tables. No other migrations run.
- Full `php artisan test --compact --stop-on-failure`: **225 passed,4 failed,3785 pending,1507 assertions,22.78s**. Failures in untouched modules: LazadaAttributeValueTest::test_enumerations_use_english_values_and_custom_input_stays_supported; StorefrontVariantDataTest::test_selection_uses_authoritative_stock_and_preserves_requested_variant; StorefrontVariantDataTest::test_payload_excludes_internal_fields_and_keeps_unknown_inventory; CatalogProductHandleTest::test_a_changed_url_key_redirects_the_previous_product_address_to_the_canonical_address. Baseline preexisting status not independently established; no blind rerun/unrelated fixes. Full log `tmp/marketing-reference-expansion/automation-full-suite.log`.

## Browser blocker / remaining acceptance

After user confirmed environment reconnection, one CUA getState attempt timed out/kernel reset. No input or generation was performed in that browser attempt. Browser work paused. Prior8 timeouts retained; conservative aggregate9 Test error, root correlation incomplete, requires explicit user decision before more affected browser repair/retries. No terminal-browser/screenshot workaround attempted. **Desktop/mobile actual browser interaction and layout/pixel acceptance remain unrun**, not passed. New PNG inspection is image-asset verification, not a webpage screenshot.

All localhost links refer to the project computer. Live site remains unchanged. Synchronization/release awaits local UI acceptance and explicit user approval.

## Decisions / limits

1. Canonical workspace retained instead of a new worktree to comply with AGENTS; cost: concurrent edits require careful baseline/hash review.
2. Broad product groups expose a disclosed relevant candidate window of60 products to AI, ranked by prompt keywords then ID. Configured selections stay complete; oversized CMS/provider contexts fail explicitly before the respective call. Cost: AI can miss an otherwise suitable product outside that window; refine sources/direction. This is not an arbitrary selection cap.
3. Fixed voucher amounts/conditions are server-appended verified text; model money remains forbidden. Cost: fixed-amount image hooks stay nonmonetary; captions retain actual terms.
4. Timing settings are saved preparation information only; no timer/job/cron worker exists in this phase.

Review Important findings were covered by red→green regressions; no deferred minor findings. Extra source observations fixed in the same source edits: unused query removal and accurate AI-selection provenance. Future LLM validation failures pause safely and retain diagnostics; they are not silently normalized or retried.

## Main changed files

New models `MarketingPostAutomation`, `MarketingAutomationRun`; targeted migration; services `MarketingPostAutomations`, `MarketingAutomationSources`; expanded `MarketingAutomationController`; `resources/views/marketing/automation/{index,workspace}.blade.php`; `public/marketing-studio/automation.js`; routes and narrow manual availability gate. Reused/extended `MarketingFreePosts`, `MarketingFreePostProvider`, canonical dynamic-collection predicate. Feature/JS tests and plan/issue ledger updated. Earlier Automation nav/header work remains preserved in the same dirty workspace.

## Follow-up: clean-baseline causal verification (2026-11-06)

Execution connection restored and confirmed with one read-only pwd/HEAD check. No further provider generation, Post creation, publication, deployment, scheduling or browser attempts in this follow-up. Application source unchanged during this verification.

Exported **Git HEAD7890952a application/config/routes/tests/migrations** using git archive into `tmp/marketing-reference-expansion/automation-clean-baseline`. No `.env` copied. Installed dependencies reused with Composer App/Tests/Database loaders remapped to snapshot paths; `APP_BASE_PATH` points there. Test-only key and SQLite `:memory:` forced; baseline Tests\TestCase safety guard retained. Composer manifest/lock unchanged from HEAD. Current phpunit.xml's unrelated extra safety flags were not imported: original HEAD phpunit.xml used. Baseline error stack traces explicitly point at snapshot application classes. Source hash example: snapshot SearchVisibility blob `137d5ed0daed63d17470f0700739f3105e1950eb` equals HEAD blob. No reset/worktree switch or original file replacement.

Command (run once):

```powershell
php vendor/phpunit/phpunit/phpunit -c tmp/marketing-reference-expansion/automation-clean-baseline/phpunit.xml --bootstrap tmp/marketing-reference-expansion/automation-baseline-bootstrap.php --filter='test_enumerations_use_english_values_and_custom_input_stays_supported|test_selection_uses_authoritative_stock_and_preserves_requested_variant|test_payload_excludes_internal_fields_and_keeps_unknown_inventory|test_a_changed_url_key_redirects_the_previous_product_address_to_the_canonical_address' tmp/marketing-reference-expansion/automation-clean-baseline/tests/Unit/LazadaAttributeValueTest.php tmp/marketing-reference-expansion/automation-clean-baseline/tests/Unit/StorefrontVariantDataTest.php tmp/marketing-reference-expansion/automation-clean-baseline/tests/Feature/Admin/CatalogProductHandleTest.php
```

**Result: the same four tests reproduce on clean HEAD:3 errors,1 failure,5 assertions,3.511s.** Log: `tmp/marketing-reference-expansion/automation-clean-baseline-results.log`. This establishes baseline failures with the installed dependencies, not merely an inference from an aggregate run.

| Exact test | Baseline error/assertion and cause |
| --- | --- |
| `Tests\Unit\LazadaAttributeValueTest::test_enumerations_use_english_values_and_custom_input_stays_supported` | ValidationException “Review the value and supported input type for color.” Test line13 supplies multiSelect `Blue,Red` without max_value_count. `app/Services/Marketplace/LazadaAttributeValue.php:39` rejects multiple values without a valid limit; throw at line13. Same fixture/validator in HEAD and working source. |
| `Tests\Unit\StorefrontVariantDataTest::test_selection_uses_authoritative_stock_and_preserves_requested_variant` | Target class [config] does not exist. Test line16 calls selection; service lines36/43 reads `$variant->product`; ProductVariant.php:12 belongsTo causes DB configuration resolution. Test extends PHPUnit\Framework\TestCase rather than Laravel Tests\TestCase, and supplies no owner relation. Baseline stack establishes this exact lazy-loading path. |
| `Tests\Unit\StorefrontVariantDataTest::test_payload_excludes_internal_fields_and_keeps_unknown_inventory` | Same config container error at test line27; service lines16–17 reads unprovided product relation. Same baseline path through ProductVariant.php:12. |
| `Tests\Feature\Admin\CatalogProductHandleTest::test_a_changed_url_key_redirects_the_previous_product_address_to_the_canonical_address` | Test line89 expects `/products/studio-headphones-2026?variant=1`, receives URL without query. SearchProducer.php:30 inserts search_redirects on handle change. SearchVisibility.php:16–23 handles that old path **before** controller and redirects `to($target,301)` without query. StorefrontController.php:87 already includes `$request->query()`; it is bypassed by earlier middleware. |

**Causal contrast for Catalog:** a test-only subclass of the unchanged baseline CatalogProductHandleTest calls `withoutMiddleware(SearchVisibility::class)` in setUp. No application source edited. Same exact failing inherited test then **passes:1 test,5 assertions,2.738s**. Command uses the same baseline configuration/bootstrap, with `--filter=test_a_changed_url_key_redirects_the_previous_product_address_to_the_canonical_address` and `.../tests/Feature/Admin/CatalogQueryDiagnosticTest.php`. Log `tmp/marketing-reference-expansion/automation-catalog-query-diagnostic.log`. This isolates the competing middleware redirect rather than incorrectly blaming the controller or Automation.

No repair to these unrelated modules. Conservative observed failure counts including the baseline contrast: Lazada2, Variant container4 (**Attention**), Catalog2; earlier historical counts unavailable. Baseline reproduction was diagnostic, not a claimed repair or reset of the issue ledger. No more retries planned. Browser historical Test error remains paused, without any new browser attempt.

**Existing Draft recheck at2026-11-06T12:27:40Z:** run `68277a2e-142d-4cf7-93ef-255524d44fee` completed; Post `d0156168-9931-43d3-aa05-d03cf4bca89a` remains Draft, targets empty, one asset. Real review controller render200 and title present. PNG1024×1280 with unchanged SHA256 `407ecea8c95846222b4c4b89d1319e3b40311d558df90b70351ce8c0fe60e906`. Http::preventStrayRequests confirms zero outbound requests. Evidence `tmp/marketing-reference-expansion/automation-existing-draft-check.json`. Did not call generation/step or create records in this recheck.

Outcome: local manual Automation generation chain remains usable with existing real Draft evidence. These four baseline failures do not block that chain, but the full project suite is not green. Desktop/mobile browser layout acceptance remains unrun due prior browser blocker; no release claim or synchronization authorization inferred.

## UI correction — reuse approved AI Post components

User supplied current screenshots of the approved AI Post cards/brand/platform controls versus the Automation form, and explicitly requested rescan/correction of `/admin/marketing/automation/ai-posts/new?preset=spotlight`.

Findings grounded in those supplied screenshots and current source: Automation had replaced visual Post Type/image cards with a select and plain radios; Bootstrap form-check floated checkbox inputs combined with the global38px toggle styling caused the stair-step labels; the page lacked the existing brief grid, brand preview and matching live summary. This was a UI implementation error, not a new design direction requested by the user.

Correction directly reuses existing `marketing-ai-brief-preview/brief.css`, `marketing-ai-brief-preview2/free.css` and `FreeBriefCore.types` (names/icons/descriptions/notes). New `marketing/automation/editor.blade.php` uses the approved brief-grid/card/section-label, content-type buttons, platform-choices labels, brand-preview and color-field, and brief-summary patterns. Weekday labels use the same flex pill pattern; no form-check/form-check-input markup remains. Theme, language, voice, platforms, source selections, weekday/time and Post Type/image mode update the sidebar and retain existing save payloads/session state. Readonly actual company/logo data is supplied by the existing BusinessIdentity. No generation service, schema, provider prompt or pricing behavior changed in this UI correction.

Changed UI files: `resources/views/marketing/automation/{index,workspace,editor}.blade.php`; `public/marketing-studio/automation.{css,js}`; readonly brand variable added in `MarketingAutomationController.php`; feature/JS regression assertions updated. Shared manual AI Post source CSS/data files themselves were not edited. No business records saved, no new Post/provider calls, no publishing/scheduling/deployment.

Verification:

- Intentional initial reds: missing shared stylesheet contract (PHP7.20s) and zero visual choice cards (JS). Fixed by actual component reuse.
- `php artisan test --compact --filter='MarketingAutomationManualTest|MarketingAutomationPageTest|MarketingFreePostTest'`:45 passed,287 assertions,17.39s.
- `node --test tmp/marketing-reference-expansion/automation-ui-render-check.cjs tests/javascript/marketing-automation.test.cjs`:5 passed,2576ms. Uses actual server-rendered editor HTML plus actual shared styles/scripts:9type cards,2image cards,7days, computed float:none and flex labels, source removal, save serialization, card selection, weekday/color/summary updates and simulated reload retention. No outbound requests.
- Actual company editor rendered HTTP200,15985HTMLbytes, shared styles loaded in markup, no floating check classes,0provider calls (`automation-ui-render.php`). Syntax/diff checks pass. This is server/DOM/CSS evidence, **not** pixel/layout acceptance in a real browser.
- User's new screenshots/rescan request explicitly resumed one browser inspection attempt. CUA getState again timed out/kernel reset without inputs. Stopped immediately; no browser workaround. Conservative browser history10, existing Test error retained, no further retry. Fresh desktop/mobile screenshots remain blocked. User-provided screenshots were the visual target; no claim of a completed live-browser audit.

Current local page is ready for the user's visual review at the same URL. Prior real Draft remains untouched. No synchronization authorization inferred.

### UI handoff correction: image-card overflow remains behind a visual gate

The user's next screenshot exposed an unverified browser-layout bug, so the prior “ready for visual review” handoff above was premature. It is **superseded**: do not treat the earlier DOM pass as browser/pixel acceptance.

Exact cause: Bootstrap `legend{float:left;width:100%}` relies on `legend+*{clear:left}`. Automation placed the hidden image_mode field between the legend and the card grid; the clear rule applied to a display:none input and failed to clear the legend. The grid was displaced alongside the full-width float and overflowed toward the summary. Previous DOM probe omitted this Bootstrap interaction.

Fix: hidden image_mode field moved outside the fieldset; all Automation legends scoped to float:none; content grid/cards min-width:0. Regression first reproduced float:left, then passed after the fix. No generation/business behavior changed.

`node --test tests/javascript/marketing-automation-layout.test.cjs tests/javascript/marketing-automation.test.cjs`:5 passed,2561ms. Actual server render200/16035bytes,3 affected PHP tests31assertions7.95s, actual rendered DOM acceptance probe1pass626ms. These remain code/DOM checks, not browser geometry proof.

New browser discovery clarified stale historical IDs: browser2 is now IAB, Chrome4 inventory timed out and was not retried; separate Chrome3 inventory succeeded and identified a live local browser. A new independent QA tab opened the exact requested localhost URL successfully but reached **Admin Portal Login**. No user tabs were navigated, no credentials entered, no session bypass or demo persona used. Tab marked handoff, user asked to log in themselves (no password sharing). Awaiting login before desktop/narrow-screen screenshots, boundary measurements, card toggles and remaining page inspection. Current state: **visual acceptance pending authentication, not ready for handoff**. No paid calls, Post creation, publishing, scheduling or deployment in this repair.

### Actual browser acceptance after user login

User confirmed login. The authentication gate above is now closed for Chrome3; the historical Chrome4/getState timeout history remains recorded, not reset or retried. Actual localhost editor inspected with supported CUA browser APIs, screenshots captured, saved and reopened for visual inspection.

| Viewport | Actual browser evidence |
| --- | --- |
|1536×674 desktop|Document scrollWidth=clientWidth1530. Image cards both112px high at x346.8–717.2 and729.2–1099.6; summary begins1150.4, leaving50.8px separation. Legend float:none. Both cards stay inside form right1126.4; no overlap or horizontal overflow.|
|768×1024 tablet|Document scrollWidth=clientWidth762. Image cards50.8–375.2 and387.2–711.6 within form24–738.4. Summary below the form. Screenshot visually checked.|
|390×844 mobile|Document scrollWidth=clientWidth385. Image cards vertically stacked x44.8–340.0, widths295.2, heights99.3. No form inputs/selects/textareas/type cards cross viewport. Summary follows form with24px gap. Weekdays wrap into aligned rows, not stair-stepped. Screenshots visually checked.|

Browser interactions actually exercised: Poster→Meme→Poster; Single→Multiple→Single with matching summary; Facebook/Instagram/TikTok control changes; Mon/Wed/Fri and Sun selection/removal; English→中文→English; voice change and restore; RAM keyword search, adding one actual ProLab source, removing it, clearing keyword (zero remaining results); actual company logo loaded. Captured browser console error list empty. No Save or Generate clicked. Test-only choices restored to default (Poster,single,all3platforms,no selecteddays/sources,English,originalvoice). Viewport override reset; independent QA tab kept as deliverable; user's other tabs untouched.

Evidence directory `tmp/marketing-reference-expansion/automation-visual-qa/`: `desktop-images.jpg`, `desktop-platforms-days.jpg`, `desktop-brand.jpg`, `tablet-images.jpg`, `mobile-images.jpg`, `mobile-days.jpg`; final numeric measurements in `final-browser-measurements.json`. Desktop/tablet/mobile saved images reopened with view_image and inspected. Current card overflow repair is **browser-verified**, superseding the earlier pending-auth state. No new paid generation, Draft changes, publishing, scheduling, push or deployment.

## CTA simplification — user approved

Removed the standalone Start a new generation button and the additional progress-panel View draft link. Two persistent primary actions remain: **Generate Post** and **Save automation**. Generation action changes with state: Generating… (disabled during actual pipeline), View progress (read-only for requesting state), Continue generation (explicit unfinished safe stage), Finish saving (one explicit local-save attempt), View drafts (completed), View issue (unknown/unconfirmed). No unknown record is cleared or automatically retried. Existing run/session pointer retained for review. Recent-generation links now read status only; they do not advance paid steps simply by opening history.

Unknown details explicitly explain possible provider charges and retained record ID. Completed main action navigates to the existing review; no duplicate CTA link. Saving settings remains separate and never invokes generation. No service/model/schema/provider change, no server record reset. Files: `public/marketing-studio/automation.js`, removal of obsolete style in `automation.css`, editor button label, JS regression tests.

Verification:9 JS tests passed1511ms (including unknown/requesting GET-only, pointer retention, single creating request, busy labels, completed action, single-attempt failed local-save protection, shared cards/layout).16 PHP tests124 assertions6.88s passed for Automation pages/settings/mock generation. Syntax and diff checks passed. Full suite not repeated for this frontend-only change; previous clean-baseline four failures remain separately documented.

Actual Chrome3 verification used **existing** unknown run8dd and completed run682, no Generate/Save clicked: View issue reads status and expands retained-charge warning; no Start button; exactly two main buttons. View drafts actually opened existing review682 successfully, then returned. Fresh editor shows Generate Post / Save automation only. Desktop1536×674:scrollWidth=clientWidth1530. Mobile390×844:scrollWidth=clientWidth385; buttons295.2px wide, right340, no overflow. Temporary viewport override reset. Final screenshots `automation-visual-qa/cta-default.jpg`, `cta-mobile.jpg`, `cta-issue.jpg`; saved desktop screenshot reopened/inspected. New-generation pipeline was exercised with mocked requests only, not real AI. No new Post or costs.

Diagnostics retained: AUTO-CTA-WAIT-01 one5s asynchronous issue-opening wait expired; a later enabled/control state observation showed the read-only request completed and details open, no repeat generation. Initial viewport-transition frames were rejected and overwritten after stable locator actions; only stable screenshots accepted. Test fixture async resolver error observed twice then corrected (not mislabeled as production failure/TDD behavior red); generating-label and single-local-save-attempt regressions each observed fail before fix. No outstanding failure from this CTA change. Pending user acceptance/synchronization approval; no push/deployment.
