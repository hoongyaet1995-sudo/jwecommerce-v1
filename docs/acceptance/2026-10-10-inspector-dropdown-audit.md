# Inspector dropdown audit

2026-10-10. Static source audit of all 35 canonical section types, SectionInspector and its mounted settings components. Archetype cards are variants, not separate Inspector implementations. No application or page data changed; no all-type browser acceptance claim.

## Layout dropdowns still present

| Section | Dropdown | Recommended control |
| --- | --- | --- |
| media_text_split | Layout preset: Classic, Editorial Hero, Editorial Story, Editorial Story Cards, Story with Steps, Editorial Cover | Six composition diagram cards |
| media_text_split | Desktop image position; CTA style (conditional editorial layouts) | Mirrored split diagrams; button/text-link previews |
| rich_text | Default / Manifesto | Text composition cards |
| chapter_block | Story, Perspective, Principles, Process, Delivery, Policy, Actions | Seven composition cards; existing Policy early return means selector is not shown there |
| media_gallery | Default, Editorial Montage, Editorial Cards | Gallery composition cards |
| feature_cards | Default, Editorial Columns, Service Highlights; conditional legacy Values List | Card/column/icon diagrams |
| media_gallery / feature_cards | Editorial columns 2/3/4 | Column diagrams |
| cta_strip | Classic, Image Banner, Editorial Band, Help Panel | Four composition cards |
| cta_strip | Image-banner height | Height diagrams |
| faq_accordion | Classic / Minimal | Accordion diagrams |
| values_list / legacy feature_cards values-list | Row spacing | Compact/normal/spacious diagrams |

CTA banner height still incorrectly offers editorial-band/help-panel, which are layout presets, not height values. Repair option ownership without migrating saved pages blindly. Chapter policy selector availability is a separate capability issue to verify before changing UI.

## Shared visual dropdowns

- SectionImageSettings helper produces five controls: image ratio, fill mode, width, shape, plus ratio's original/default choices. Position already uses a 3x3 grid. Width/shape may be disabled by geometryLocked.
- SectionBorderSettings: default, none, top, bottom, both. Use boundary diagrams.
- SectionInspector Background Canvas Style: use swatch/gradient cards rather than composition diagrams; preserve custom background controls and section-surface priority.
- CustomBackgroundControls Spotlight Origin Position: use a position grid with gradient preview.

Correction: the five-count above refers to ratio, fill, width, shape and position overall; only four of these are dropdowns. Position is already visual.

## Keep data and timing choices separate

- Hero Slider and Product Listing autoplay intervals: compact numeric segmented choice or slider; diagrams do not explain time.
- Product source category/collection, Shop Navigation destinations, Promotion Highlights promotion, Newsletter subscriber coupon: searchable data selectors, not fake layout thumbnails.
- Delivery information source: automatic/manual segmented cards with source explanation.
- Testimonial rating: clickable stars rather than dropdown.
- ContactSections enquiry select and SectionRenderer customer-facing form select are storefront form inputs, outside Inspector audit scope.
- PageAdmin selects are page-level controls, outside Section Inspector scope.

## Already visual dedicated layouts

Hero Slider layout, Shop Navigation layout/columns/banner proportions and height, Product Listing layout/columns/image shape, Policy layouts, Payment layouts, Promotion Highlights composition and location composition already use choice cards. This does not mean every subsidiary setting in these families is visual.

Recommendation: first convert all layout presets and composition choices using one reusable diagram-choice primitive; next convert shared image/border/background controls. Preserve existing patch callbacks, values, save contracts, renderer behavior and conditional visibility. Verify layout switches, save/refresh and shared Builder/public rendering before calling migration complete.

Source evidence: resources/admin/src/components/pages/{SectionInspector,MediaTextSplitSettings,RichTextSettings,ChapterSettings,CtaStripSettings,ShippingSettings,ValuesListSettings,SectionImageSettings,SectionBorderSettings,ProductListingSettings,ShopNavigationSettings,PromotionHighlightSettings}.tsx and resources/cms/page-contract.v1.json.

Implementation check: 2026-10-10. Convert visual selects using shared VisualChoiceSelect; preserve option values and callbacks. Before run: targeted Inspector tests verify diagram selection, canonical patches and CTA valid heights; existing select interaction tests updated for buttons. Unexpected failures for this implementation: 0. Timing/token usage unavailable.
Targeted run: 16 passed, 1 failed due to a remaining old test dispatching select change on a diagram group. Unexpected issue count 1. Updated remaining test to click Left card. Next run verifies corrected test and unchanged callbacks.
Second targeted run: 17/17 passed. Admin build passed in 16.05 seconds. Full tsc remains blocked by existing project diagnostics; no diagnostics in VisualChoiceSelect or converted settings modules. Builder verified six Media Text Split diagram cards and selected Editorial Story Cards; selecting same card persisted through canonical draft to version 105, preserving content. Next final build includes accessible background/spotlight labels and Rich Text punctuation repair.
Expanded run: 20 passed, 2 failed in old UnifiedInspector assumptions (fixed 27 group count and removed Text font dropdown). These controls had already changed before this phase. Updated checks to applicable groups, three tabs, absence of layout dropdowns and actual Change font button. Unexpected test-assumption issue count 2 including earlier remaining select event. Final build passed 13.70s; next focused expanded run checks updated assertions.
Expanded retry: 21 passed, all-preset loop exceeded default 5s limit after adding DOM parsing. Distinct test-runtime issue count 1. Split loop into individual named archetype tests; no application change. Next run checks each archetype independently.
Final result: 68 UnifiedInspector tests passed, including each registered archetype independently; 17 focused tests passed (85 total). Admin final build passed. Actual Builder reload confirmed saved Editorial Story Cards selected and six diagram choices; screenshot output/cms-page-style/layout-diagram-cards.png. Shared image, border, background and spotlight controls converted; data/timing/rating/source dropdowns retained. Full tsc still reports existing project errors. No publication, Git push or deployment.
Preset Content repair: initial expanded tests 76 passed, one failure because newly registered Story Cards exposes an existing text Delete button. Issue count 1; replaced with shared InspectorDeleteButton. PHP 29 tests passed. Added slot-switch ownership regression and mounted Content-switch/picker coverage. Skill updated with new-preset Content gate.
Slot-switch test failed due to an unsafe relative media URL in the test fixture (fixture issue count 1). Replaced with safe HTTPS URL; rerun verifies ownership, not unsafe URL allowance.
Fixture URL retry rejected reserved example.com (fixture issue count 2). Existing accepted fixture uses images.unsplash.com; use same policy-compliant host for ownership test.
Preset repair result: 77 frontend and 30 PHP tests passed, build passed. Builder verified Classic Add Card with two preserved entries, switching Story Cards changes Content immediately, then restored user's initial Classic. Add Section search returns six matching starters; screenshots classic-content-fixed.png and media-presets-picker.png. Shared Classic reorder controls added. Skill entrypoint updated with renderer-to-Content capability matrix, picker registration and A/B/A save verification. No publication or sync.
Three editorial presets repair: root cause is hasOwnContractField gating optional Content fields by saved key presence. Introduced renderer-specific capabilities for media text fields. Before tests: empty Hero/Story/Cover must expose text and media controls without saved values; no Add Card on non-repeatable layouts. Unexpected failures 0.
Three editorial presets completed: 76 Inspector tests passed; build passed 12.10s. Actual Builder checked Hero introduction/description/image/primary and secondary CTA, Story narrative/image/primary CTA, Cover introduction/narrative/image with CTA hidden. Restored user's initial Hero. Screenshot editorial-hero-content-fixed.png. Blank image remains blank rather than stealing an item image. Skill adds sparse-data control gating requirement. No sync.
