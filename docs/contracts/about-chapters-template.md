# About Us - Chapters (localhost)

The fourth About Us template is `about-chapters`, displayed as **About Us — Chapters**. It creates six independent editable draft sections and uses the page-level Standard container. The global Header and Footer remain store settings.

## Reusable section ownership

- `media_text_split` / `editorial-cover`: editable headline, eyebrow, introduction, description and panoramic managed photograph; optional Full Bleed through Advanced Layout.
- `chapter_navigation`: automatically derives links, numbers and labels from visible `chapter_block` sections in saved document order. Links use existing `section-{id}` anchors. Preview/public scroll updates the active link; editing mode prevents navigation.
- `chapter_block`: Story (paragraphs and wide image), Perspective (two photos and paragraphs), Principles (editable title/description rows). Numbering follows visible chapter order. Subtitle supplies the navigation label. Image Tools use stable per-item identities; reordering photographs preserves their image settings. Changing chapter layout initializes its expected content; review content after switching.
- `cta_strip` / `editorial-band`: lateral heading, description and real action link, using existing color pickers and page container.

All six starting presets are available globally through Add Section; they are not About-only components. English Inspector controls use existing shared Image Tools, colors, motion, spacing and borders. New fields and section types are validated by both PHP document contract and frontend public bootstrap.

## Bundled assets

Four illustrative generated photographs are bundled under `public/page-template-sample/chapters`. Their canonical managed IDs are in `resources/cms/about-chapters-media.json`, imported by the frontend factory and read by `AboutChaptersMediaSeeder`. Install them with `php artisan db:seed --class=AboutChaptersMediaSeeder --force`. The seeder is idempotent and installs CMS media metadata and Storage files. Future release must carry bundled photographs and execute the seeder; do not synchronize database drafts as a template implementation.

## Local evidence

Draft 34: `/admin/pages/34/preview`, slug `about-chapters-8a91e073`. Six sections, four managed photographs. Navigation 03 resolves the actual principles anchor with approximately 144px clearance. Independent Image 2 crop saves without changing Image 1. Actual 400 CSS px mobile breakpoint has clientWidth = scrollWidth = 386px and single-column chapters. Screenshots and test accounting are in the active 2026-10-08 page-template pilot acceptance log. No publish, home change, GitHub sync or deployment is included.
