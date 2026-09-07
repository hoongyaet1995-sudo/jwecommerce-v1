# Ecommerce CMS ZIP 31 Motion - local acceptance

Date: 2026-09-07
Target: C:/xampp/htdocs/jwstudio-ecommerce; /admin/pages.
Branch: codex/ecommerce-cms-motion-zip31. Local runtime sources synchronized; no GitHub or Hostinger release.

## Source and extraction boundary

Source: jw-ecommerce-dashboard (31).zip
SHA256: C47E1C7FFAA9E1E07A2E8ACBEA19ED681FCF698DF38786C328DEB041F0A64441
Private extraction: C:/Users/User/AppData/Local/Temp/jw-motion-31-source-20260907
Selected source: src/components/pages/motion/CmsMotionWrapper.tsx, SectionRenderer.tsx motion nodes, SectionInspector.tsx Entrance Motion block, PageAdmin.tsx Replay button.
Preserved preset names, timing, easing, source controls, layout classes and atomic motion roles. No ZIP mock catalog or mock persistence copied. No new assets.

## Section mapping

All 27 supported section types share CmsMotionWrapper. Announcement Bar uses the source section wrapper without atomic items. Remaining 26 functions contain all 114 source atomic nodes:

| Renderer | Atomic nodes |
| --- | ---: |
| renderHeroBanner | 6 |
| renderSplitHero | 7 |
| renderHeroSlider | 5 |
| renderRichText | 4 |
| renderMediaTextSplit | 6 |
| renderTimelineStory | 4 |
| renderStatsCounter | 1 |
| renderProductGrid | 4 |
| renderProductListing | 5 |
| renderCollectionTabs | 5 |
| renderFeaturedDeal | 7 |
| renderPricingTable | 4 |
| renderMediaGallery | 4 |
| renderVideoEmbed | 4 |
| renderBeforeAfter | 4 |
| renderInstagramFeed | 4 |
| renderFeatureCards | 4 |
| renderIconList | 1 |
| renderComparisonMatrix | 4 |
| renderFaqAccordion | 4 |
| renderContactForm | 5 |
| renderStoreLocator | 4 |
| renderTestimonials | 4 |
| renderLogoCloud | 3 |
| renderCtaStrip | 5 |
| renderNewsletterSignup | 6 |

Hero background media wrapper matched explicitly. Collection tabs retain the existing real product anchor inside the source MotionCard. Other 112 nodes mapped by owning renderer and source JSX element/class signature.

## Runtime adapters

- Optional motionPreset accepted by resources/cms/page-contract.v1.json and PageRuntimeContext bootstrap. Old documents remain valid without rewriting them.
- Existing Laravel draft autosave and publication contract retained; Apply All schedules a real draft save, not mock state.
- Editor and Laravel preview/public entry share SectionRenderer.
- Reduced-motion preference or unavailable IntersectionObserver uses static content instead of hidden/inaccessible content. Normal browser presets remain source-identical.
- Existing app toast object contract retained.
- No migrations, resets, table deletion, imports, order or Marketplace changes performed.

## Evidence

- Main runtime npm test -- --run src/cms: 139 passed, 11 files.
- Five presets tested for all section types in import contract; public bootstrap accepts supported values and rejects unknown values.
- All 27 section templates render without throwing for each preset in server/static fallback tests. These are automated structural tests, not 27 individual visual parity screenshots.
- Final npm run lint passed in both Motion worktree and main runtime. An earlier concurrent Marketplace missing-module check cleared once that task created its module; no unrelated fix attempted.
- Main npm run build passed, preserving current main Marketplace sources in the generated bundle; chunk-size warning remains.
- Authenticated Chrome /admin/pages: five presets, Replay and Apply All displayed in original Inspector style.
- Created only QA draft page 25, Motion ZIP31 QA - Local Draft (slug new-landing-page-642), two Hero sections. Not published or assigned as homepage.
- Selected Smooth Zoom, duplicated section, changed Dynamic Pop and applied to all. Reloaded browser and reopened saved draft. Export JSON confirmed both motionPreset values equal dynamic-pop.
- Laravel iframe /admin/pages/25/preview rendered successfully with no captured console errors. Desktop and 388px mobile iframe visually inspected.
- Original pages/homepage left unchanged. QA draft retained for user acceptance.

## User path

Open /admin/pages, Edit Layout, select a section, then Entrance Motion in right Inspector. Replay Motion in toolbar replays the canvas; Apply All applies the selected preset to this page. Publish through the existing page workflow when desired.
