# Unified new Section defaults — local acceptance, 2026-11-16

## Approved scope

All 173 Add Page Section cards now create new sections with the approved unified copy, JW palette fallback, Stack Sans Headline / Switzer / Inter typography, and Neutral vector image placeholders. The existing twelve shared-family layout choices use the same insertion boundary. Layout diagrams and Inspector controls are reused. No migration or deployment was performed.

## Implementation boundary

- `createNewSection` clones and normalizes only a new insertion. Existing registry/page-template factories are unchanged. Ordinary and shared-layout picker actions both call it.
- Optional canonical `starterDesign: neutral-v1` scopes inherited font stacks and default image presentation. Existing documents without this marker keep their renderer behavior.
- Page palette settings take precedence. The JW palette is a fallback when there is no page style. No new palette picker or duplicate Inspector controls were added.
- New owned IDs and references, including image slot targets and plan references, are rewritten together. Structural discriminators such as device-table subtitles and native group/slot identities remain intact.
- Neutral static SVG URLs are saved through the existing media contract. Browser and PHP media validation recognize bundled assets. Image placeholder presentation reuses the reviewed measurement/SVG generator, preserves layout CSS, adapts to the actual slot ratio and moves the prompt away from overlay text. It never writes generated data URLs back to page documents.
- Uploaded/employee-selected image URLs are excluded. Placeholder-only intrinsic attributes and state are removed on replacement. Authored alt text is preserved on resize.
- Existing form operation stays operational; new form confirmation text no longer carries the sample-only “no message is sent” notice. No form submission, payment, provider call, promotion creation or catalogue mutation was used for acceptance.
- A real save probe uncovered the pre-existing missing PHP target allowance for Device Comparison image-alt settings. It is now aligned with the existing Passport target ownership.

## Verification

1. Real mounted AddSectionModal: all 173 cards inserted, including shared-family layout selection; defaults immutable; canonical owned image URLs; actual SectionRenderer SSR output. All 346 default/edited fixture documents pass frontend import validation.
2. Backend isolated sqlite :memory:: 173 default plus 173 edited documents pass create, save, refresh and PageRuntimePayloadFactory equality (1046 assertions). An old-page sentinel remains unchanged. Original 173 legacy starter backend regression also passed.
3. Related regression: seven files / 22 tests passed, including shared libraries, layout/style controls, control-repair regressions and 1536 sample layout/palette/surface renders. Final focused placeholder/creation checks passed (six checks); final TypeScript and Admin client/SSR build evidence in the output directory. Build retains existing chunk-size warnings.
4. Browser: 173/173 official new defaults at desktop 1280 and mobile 390; both reports have zero render errors, missing images, horizontal overflow and text overflow candidates. These checks cover default layouts and available tab/slide states, not every arbitrary future content combination.
5. Authenticated local CMS QA draft **342**, created from Blank Page through the real UI. Added Hero and Sticky Stack. Edited title, description, item title/description, button copy/destination, title size/color and image URL/alt. Saved through Preview, exited, refreshed the page list, re-entered Inspector and refreshed `/admin/pages/342/preview`. Edits persisted and frontend rendered them. Replacement image is not treated as a placeholder. Remaining Neutral slots adapt to mobile; phone viewport measured clientWidth = scrollWidth = 380. No console errors observed.
6. Preservation: all **144 pre-existing page-document records** have identical fingerprints after acceptance. QA342 remains Draft, has two sections, no published_at and was not made home. Other unfinished work is retained. No GitHub, www, ecom or social changes.

Evidence: `output/section-defaults-integration-20261116/` contains scoped before files/diff, 173 insertion report, 346 save/reload report, frontend import fixtures, desktop/mobile browser reports, screenshots, build/type logs and document fingerprints.

Accounting: elapsed/token totals unavailable. The durable diagnostic history is in `output/jw-cms-integration-20261114/checkpoint.md`; intentional initial red, extraction regression, link synthesis, fixture button probes, device image ownership and frontend media registration are recorded separately, with no blind retries.
