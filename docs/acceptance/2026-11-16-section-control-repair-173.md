# Section control wiring repair — local acceptance, 2026-11-16

## Scope and correction
Reviewed all 8 issue classes / 14 cards from the prior audit. Seven actual issue classes were repaired across 8 cards; the six wide-visual cards were false positives from comparing the unset (96px) default with Spacious (96px). All six have distinct Compact 32 / Normal 64 / Spacious 96 outputs. Their source defaults and layouts were intentionally left unchanged. Prior audit conclusion is superseded for spacing only.

## Repairs
- Connected Preview Tabs: current Passport excludes unused section-level primary action/main image and 9 obsolete illustration slots. Saved values remain untouched and available in stored documents.
- Connected Metrics: connectedEntryFields derives visible per-item controls from layoutVariant. Variant 0 exposes title/value/description; variant 1 title/value; variant 2 retains media/action fields. Image target discovery shares the same capability helper. Sparse/new-item initialization uses active capabilities.
- Connected Logos: both tracks now read the same canonical item list and ScopedImage target; mirror remains aria-hidden. Data changes/deletion/reorder apply to both tracks.
- Age Guide Cards: hides unrendered section CTA; per-card destinations retained.
- Staggered Focus Cards / Contrast Image Cards: palette-owned surfaces no longer expose an ineffective Custom background & gradients entry. Existing Surface controls and old customColors values retained.
- Full Banner Background Slider: top-level copy/actions hidden because slides own visible copy/actions. Shared slide typography roles retained. Section title and old values are not deleted.
- Policy Numbered steps: hides unsupported Link & icon group; entry title/description and image regions retained.
- Existing generated AI copy capability manifest regenerated from the same Passport contract.
- Sample palette array received an explicit BrandPalette type annotation to resolve a pre-existing optional-contrast union diagnostic; runtime sample design unchanged.

## Verification
- Targeted initial red: 3 expected failures and 1 spacing pass; repaired tests 4/4 pass.
- Full regression first run: 637/639 pass. Two stale tests assumed section-level slider title and created only Metrics variant0 while demanding all variant fields. Updated ownership assertion and fixture to cover all3 variants. Final affected suites 601/601 pass; other 38 tests from initial run pass. No implementation capability was widened to satisfy assertions.
- All 173 current registry cards mounted in actual Content/Design/Advanced Inspector and rendered after changes. All 226 original targets re-inventoried against narrowed capabilities. Per-field differential artifacts under output/section-control-repair-20261116/after. No unresolved raw probes remain for the 8 repaired cards; remaining candidates belong to previously classified enum/data-source/fallback cases outside this repair.
- 173 default + 173 edited canonical documents saved/reloaded through sqlite :memory: and compared to frontend runtime payload: 346/346 exact retention. No local merchant pages used for automated fixture writes.
- TypeScript: pass. Production Admin + SSR build: pass, including generated AI contract parity. Existing font URL/build chunk-size warnings retained.
- Source defaults registry not changed; no default layout/spacing/palette normalization rollout.

## Real localhost browser evidence
New draft only: Local QA — Section wiring repair 20261116, page_id 341, eight sections; not published and not set as home. Existing draft/page sessions were left untouched.
- Tabs: no unused Images/Actions group; title edited to Local acceptance — Tabs wiring, saved, exited, refreshed and reopened with 8 sections intact.
- Metrics: actual first/second/third entry controls reflect per-variant capabilities.
- Age Guide: zero top-level CTA inputs; Slider: zero section-level title inputs; Numbered Steps: zero Link & icon groups.
- Card Surface: ineffective custom-background group absent, Surface retained.
- Wide Visual: actual computed padding 32px -> 64px -> 96px; saved 96px.
- Logo: changed first item to an existing local asset; 70 total images with 2 updated copies in Builder and in saved draft front-end, including after refresh.
- Saved customer renderer: http://127.0.0.1:8500/admin/pages/341/preview . Header/footer and persisted edited title present; 96px padding; no browser console errors. At 390px device setting, document clientWidth/scrollWidth both 380px, no horizontal overflow.
- Screenshots: output/section-control-repair-20261116/inspector-local.png and front-mobile.png.

Automation covers all 173. Real browser interaction/save/refresh checks use the eight-section dedicated draft; this is not a claim that every control on all 173 cards was manually operated in a browser.

## Boundaries
No GitHub commit/push, Hostinger deployment, production content edits, or ecom/social changes. Other dirty work preserved. Description Tag / Title decoration were not added. Elapsed/token accounting unavailable.
