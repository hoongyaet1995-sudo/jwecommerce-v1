# CMS capability / brand surface audit — 2026-10-17

Code-grounded inventory; not a claim that every preset has new browser proof. Canonical fields/save/runtime source: resources/cms/page-contract.v1.json.

## Ownership and inheritance
First Main/Sub configuration: Website Theme > Brand & Style > Theme colors. CMS Page Settings: Follow Website Theme / Custom page palette. Local Section palette > Page palette > current global theme when opted in. Missing colorSource remains legacy custom snapshot; no silent rewriting of existing/published pages. Theme is resolved runtime-only, not persisted over authored documents. Snapshot palette is fallback during loading. Selecting a saved/custom palette explicitly leaves theme mode. Default preserves authored colors; Light/Contrast selects semantic roles while raw overrides remain available when restoring Default. Sub is secondary action color; no new Secondary Surface is added. Hero/image overlays retain layout readability rules.

## Inventory
| Section | Layout selector fields | Surface | AI | Runtime |
| --- | --- | --- | --- | --- |
| `chapter_navigation` | motionPreset | Default / Light / Contrast | Existing copy only; no new composition | Editorial |
| `chapter_block` | motionPreset / chapterPreset / policyPreset | Default / Light / Contrast | Existing copy only; no new composition | Editorial |
| `hero_banner` | motionPreset / heroBannerPreset | Default only | Starter + bounded copy | Editorial |
| `hero_slider` | motionPreset / sliderPreset | Default only | Existing copy only; no new composition | Editorial |
| `split_hero` | motionPreset | Default only | Existing copy only; no new composition | Editorial |
| `announcement_bar` | motionPreset | Default only | Existing copy only; no new composition | Editorial |
| `media_text_split` | motionPreset / layoutPreset | Default / Light / Contrast | Starter + bounded copy | Editorial |
| `rich_text` | richTextPreset / motionPreset | Default / Light / Contrast | Starter + bounded copy | Editorial |
| `timeline_story` | motionPreset | Default / Light / Contrast | Existing copy only; no new composition | Editorial |
| `stats_counter` | motionPreset | Default / Light / Contrast | Existing copy only; no new composition | Editorial |
| `product_grid` | motionPreset | Default / Light / Contrast | Existing copy only; no new composition | [{"field": "productIds", "fact": "productIds.valid"}] |
| `product_listing` | motionPreset / listingPreset | Default / Light / Contrast | Starter + bounded copy | [{"field": "sourceId", "fact": "listing.sourceId.valid"}] |
| `collection_tabs` | motionPreset | Default / Light / Contrast | Existing copy only; no new composition | [{"field": "tabs.*.sourceId", "fact": "tabs.*.sourceId.valid"}] |
| `featured_deal` | motionPreset | Default / Light / Contrast | Existing copy only; no new composition | Editorial |
| `pricing_table` | motionPreset | Default / Light / Contrast | Existing copy only; no new composition | Editorial |
| `media_gallery` | galleryPreset / motionPreset | Default / Light / Contrast | Existing copy only; no new composition | Editorial |
| `video_embed` | motionPreset | Default / Light / Contrast | Existing copy only; no new composition | Editorial |
| `before_after` | motionPreset | Default only | Existing copy only; no new composition | Editorial |
| `instagram_feed` | motionPreset | Default / Light / Contrast | Existing copy only; no new composition | Editorial |
| `feature_cards` | featureCardsPreset / motionPreset | Default / Light / Contrast | Starter + bounded copy | Editorial |
| `values_list` | motionPreset | Default / Light / Contrast | Existing copy only; no new composition | Editorial |
| `icon_list` | motionPreset | Default / Light / Contrast | Existing copy only; no new composition | Editorial |
| `comparison_matrix` | motionPreset / comparisonPreset | Default / Light / Contrast | Existing copy only; no new composition | Editorial |
| `faq_accordion` | motionPreset / faqPreset | Default / Light / Contrast | Starter + bounded copy | Editorial |
| `contact_form` | motionPreset / contactPreset | Default / Light / Contrast | Existing copy only; no new composition | Editorial |
| `store_locator` | motionPreset / contactPreset | Default / Light / Contrast | Existing copy only; no new composition | Editorial |
| `testimonials_slider` | motionPreset / testimonialPreset | Default / Light / Contrast | Existing copy only; no new composition | Editorial |
| `logo_cloud` | motionPreset | Default / Light / Contrast | Existing copy only; no new composition | Editorial |
| `cta_strip` | ctaPreset / motionPreset | Default / Light / Contrast | Starter + bounded copy | Editorial |
| `newsletter_signup` | motionPreset / newsletterPreset | Default / Light / Contrast | Existing copy only; no new composition | [{"field": "newsletterCouponId", "fact": "newsletterCouponId.valid"}] |
| `delivery_table` | motionPreset | Default / Light / Contrast | Existing copy only; no new composition | Editorial |
| `policy_content` | motionPreset / policyPreset | Default / Light / Contrast | Existing copy only; no new composition | Editorial |
| `payment_methods` | motionPreset | Default / Light / Contrast | Existing copy only; no new composition | Editorial |
| `shop_navigation` | motionPreset / shopNavigationPreset | Default / Light / Contrast | Existing copy only; no new composition | Editorial |
| `promotion_highlights` | motionPreset | Default / Light / Contrast | Existing copy only; no new composition | Editorial |

## Sources and limits
Preset-sensitive manual capabilities: inspectorCapabilities.textRoles, repeatedItemContract, imageTargets. Schema presence is not proof of rendered support. Layout selector names above are manifest fields, not a promise every variant is AI-ready.

AI new composition remains seven seeded types. Copy modifications are bounded to allowed text/existing item fields; FAQ answers, catalog identity/facts, links and media are protected. Images use separate approved editorial targets. AI planner sees allowedSurfaces; new composition may choose only default/light/contrast and server rejects unsupported overlay combinations. Improve/selected-section copy does not modify layout/surface.

## Expansion gate
Each new AI preset needs sparse/empty-state controls, media/links, canonical save/refresh, actual customer render, phone, surface/contrast and source ownership checks. Until evidence exists keep it out of composition allowlist.

## Acceptance IDs
BR-01 Resolve global theme and custom/brand modes without system-page overrides.
BR-02 theme/custom canonical roundtrip and legacy preservation.
BR-03 Live theme facts never mutate saved draft/published hash.
BR-04 Section palette/Default overrides keep precedence; semantic Surface colors readable.
BR-05 Builder/AI/Inspector/customer share resolver; public uses server facts, no admin fetch.
BR-06 Planner limited to supported semantic surfaces.
BR-07 Only acceptance draft opts in; no batch rewrite or publishing.

## Audit gaps found during this phase
- Mounted Inspector suite: editorial-journal-rows absent from the starter registration expected by test.
- Photo Marquee repeatable Content editor assertion fails.
These are existing capability-surface mismatches found by the wider audit; neither is opened for new AI composition. Remaining379-suite results:377 passed,2 failed. Do not report the entire Inspector suite as green.


## Reliability-phase audit resolution
The prior two findings are resolved: Editorial Journal Rows is registered; Photo Marquee had an incorrect generic test location (image URL is intentionally in shared ImageTargetInspector), not a missing text editor. Corrected test edits actual shared image URL, retains add/reorder/delete assertions. All383 mounted Inspector cases pass. AI capabilities directory now explicitly offers19 layout values across the7 existing composition types; other types are still excluded. This expands layout choice within accepted field shapes, not arbitrary new HTML or all-section AI eligibility.
