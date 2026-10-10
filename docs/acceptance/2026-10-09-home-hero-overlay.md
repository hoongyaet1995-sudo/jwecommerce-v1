# Home landing hero: Editorial Overlay

Scope approved: reusable hero_slider layout, diagram cards, per-slide content/image/actions/caption, existing cinematic layout preserved. Header/footer, subsequent sections and release are outside this phase.

Baseline: codex/social-crm-identity at f76a9536066e136002f282c2ae3da6c3406c93a4. Existing CheckoutService, inventory tests and acceptance/contracts edits are unrelated and preserved.

Asset: independently generated clean photograph; editable text/actions are rendered by CMS. Photo is an approximation of the approved concept, not its original pixels.

Issue ledger:
- Initial source path lookup used an incorrect resources/js path; located actual resources/admin/src paths. Resolved tooling lookup, no test failure.
- Schema script initially assumed section.types; corrected to section.sectionTypes before saving. Resolved tooling lookup, no test failure.
- Asset/picker script encountered Windows default text encoding; switched explicit UTF-8 before writing picker. Resolved tooling error, no test failure.
- Historical test accounting unavailable. Elapsed/token usage unavailable unless measured by command.

Validation run 1: focused EditorialOverlaySlider, global Inspector rendering and runtime bootstrap tests. Purpose: added slides/empty/single layout, canonical bootstrap and legacy preset regressions. Changes: new renderer/preset/caption/diagram cards. Result: 41 tests passed (3 files), 3.64s.

Backend contract and production build: pending. Real Builder save/refresh and desktop/mobile customer preview: pending.

Build run 1: npm run build; verifies bundled renderer and CSS. PHP run 1: PageDocumentContractTest; canonical contracts. Results pending.

PHP run 1: 1 failed, 25 passed. Root cause: starter photo used /images URL, outside canonical managed-media policy. Count 1, corrected starter/reference to managed UUID URL and copied local asset into CMS storage; policy unchanged. PHP run 2 verifies this change.
Build run 1 passed in 14.93s. New build required after asset reference fix.

PHP run 2 passed: 26 tests, 167 assertions, 2.51s; media issue resolved. Frontend run 2 at 20:04:00 after headline/asset changes: 12 tests passed, 5.92s. Build run 2 after managed asset reference change: pending completion output. Ledger entry for frontend run 2 was recorded after invocation, an accounting deviation; no unexpected test failures.

Browser review found duplicate section text/buttons and redundant Badge in the new layout. Hidden via layout capabilities (retaining old stored fields). New layout exposes slide-owned content only. Follow-up focused tests/build required for this change. Build run 2 passed in 14.11s.

Validation run 3: focused renderer/Inspector tests after capability cleanup and reduced-motion guard; build run 3 for final browser reload. Commands: npm run test -- --run src/components/pages/EditorialOverlaySlider.test.tsx src/components/pages/UnifiedInspector.test.tsx src/components/pages/UnifiedInspectorRendering.test.tsx; npm run build. Results pending.

Browser save issue: 422 managed media asset does not exist. Filesystem copy lacked CmsMediaAsset registry. Count 1 for this distinct runtime-reference issue. Added idempotent install command with conflict guard; tests validate registry plus managed storage. Browser request diagnostic captured through CDP. PHP focused installer run 1 and install on local environment now required.

Browser save/exit, refresh and customer-preview slide 2 navigation now passed. Final audit: map slide buttons to their item controls; caption follows shared description typography; default CTA uses actual /shop route (no /collections index route). Run 4 focused renderer regressions plus build required for these final fixes. Installer tests passed 2 tests / 8 assertions / 3.05s. Build run 3 passed 14.95s; frontend run 3 passed 12 tests / 5.92s.

Run 4 passed: 7 focused tests / 2.63s; build 13.77s. Final timer dependency audit adds sliderPreset so switching layouts recomputes reduced-motion policy. Build run 5 required for this dependency-only change; final browser screenshots will use that build.

Source-image final comparison identified interim starter wording and over-opaque panel. Restored approved headline/body/button/caption copy, translucent 65% panel and plain serif caption; photo remains independently generated. Typography capability list follows actual slide text. Run 6 focused rendering/Inspector tests and build for these visual corrections; results pending.

Run 6 passed: 12 tests / 5.35s, build 14.45s. Browser copy correction found single-line caption input strips intended newlines; changed new caption control to textarea. Final focused test/build required for this input change, then browser save/refresh screenshots. Run 5 build passed 16.74s.

Final focused test passed 4 tests in 1.82s; final build passed 20.35s. Actual Builder: insert card, caption edit, layout round-trip, save/exit, refresh, slide 2 image/title/dot switching, customer slide 2 persistence, deletion back to single slide, button-click Inspector item focus all verified. Final draft page54 / blank-e3de786d; no publishing or homepage selection. Browser selector wait once expired during async editor hydration; subsequent observed DOM showed loaded controls, resolved without code changes. Desktop/mobile final screenshots pending motion settle. Token usage unavailable. Future release must run cms:install-editorial-hero-asset before starter use; canonical media policy unchanged.

## Final local acceptance

- Done: shared reusable hero_slider Editorial Overlay preset; existing Cinematic default preserved. Diagram layout cards; slide-owned text, image, two optional actions, multiline optional caption. Typography/colors/image cropping/container/motion use shared Inspector contracts.
- Browser: draft54 Home & Deco — Hero, preview /admin/pages/54/preview. Insertion, save/exit, refresh, layout round-trip, second slide creation/render/save/customer navigation, removal back to single slide and button-to-item Inspector focus verified.
- Final desktop: canvas width 1525.6px, no horizontal overflow, photo loaded. Final mobile: viewport390, content380 (scrollbar), hero380x540, no overflow.
- Evidence: output/home-hero-overlay/desktop.jpg and mobile.jpg. Motion settled and image-ready checked before final captures; viewport reset.
- Final caption textarea change: focused tests4 passed (1.82s), build20.35s. Earlier focused bootstrap/Inspector/renderer tests passed; PHP canonical contract26 tests167 assertions and installer2 tests8 assertions passed.
- Save registry issue accounting clarification: one unsuccessful Exit and one observed 422 response before registry repair; at least2 affected attempts, earlier silent autosave attempt count unavailable. Resolved after local asset registry installation; no retries after a known authentication/environment blocker.
- Limitation: photograph independently regenerated, not original mockup photo pixels. Existing global support chat can overlap the lower-right caption in short desktop viewports; header/footer/chat behavior was outside this first-section scope. New layout video field is not offered in Inspector; only photographic starter accepted visually.
- Local only. No commit, push, production deployment, homepage change or full landing-template registration. Future release must install bundled managed asset using cms:install-editorial-hero-asset. Unrelated dirty baseline preserved.
- Elapsed totals and token usage unavailable; per-command durations above are measured outputs.
