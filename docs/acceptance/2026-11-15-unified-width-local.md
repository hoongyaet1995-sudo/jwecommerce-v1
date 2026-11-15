# Unified website width — local acceptance

Local only in `C:/xampp/htdocs/jwstudio-ecommerce`. No GitHub synchronization, deployment or production writes. Registration remains 161 Sections.

## Shared rule

- Website Content Width supplies the default width and gutter for Header, Footer and page content.
- An omitted page width now means Follow website, including Page Settings and renderer variables. Explicit saved Standard/Wide/Full values remain valid overrides.
- New page creation, template previews, all page-template Section factories and all 161 Add Section starters default to page-aligned inheritance. Templates no longer inject their own Full/Wide/Standard defaults.
- An explicitly saved Section Full Width remains authoritative. It also supplies full width and zero outer gutter to native layout content.
- Native layouts own their gutter once. Removed duplicated outer gutter for those layouts, and replaced the native mobile fixed 16px with the shared gutter when inherited; legacy explicit page overrides retain the fallback.

## Existing-data handling

Scanned 124 existing pages / 141 draft and published documents: 52 omitted widths, 47 site, 40 Full, 2 Wide. Omitted values stay absent in JSON and inherit through the shared renderer.

Changed only 40 draft page-width fields from Full to site: 8 installed starters with exact unchanged installation-receipt hashes, plus 32 known agent-owned local acceptance copies. Captured complete raw document backups before changes, and saved through PageDraftService with protected revision kind `width-cleanup`.

Preserved Technology page 28's explicit Wide (draft and published), whose intent cannot be proved from an unchanged template receipt. Preserved all existing explicit Section Full Width values (106 draft/published document occurrences). No saved Section fields, copy, media, actions, palette or layout presets were changed. Old revision documents remain readable and unchanged; explicitly restoring an old override continues to restore that override.

`preservation.json` confirms all 141 original documents match their original canonical hashes after accounting for only the 40 approved width-field changes. Global settings restored exactly to original Wide; the all161 QA page restored to its baseline.

## Acceptance

- 59 frontend tests / 7 suites passed. Includes all161 default inheritance and registered canonical import, explicit page and Section overrides, native gutter ownership, and template independence.
- All161 registered documents passed 644 canonical page-width save/refresh operations plus161 explicit Section Full Width saves and runtime reads. Ran inside a transaction and rolled back all temporary records.
- Browser checked all124 existing local preview pages against their authored/inherited widths: no frame-width mismatch. One large batch timed out; its unreturned results were excluded, recovered and checked in smaller returned batches.
- New QA page339 renders all161 Section frames. Changing website Standard made all161 frames1280px and Header1280px; Full made all161100%. Restored websiteWide.
- Real Page Settings: changed QA page to Wide, saved, reloaded and reopened, value persisted. Frontend remained1440px while website wasFull. Restored Follow website.
- Real Section Inspector: changed first QA Section toFull Width, saved/reloaded, pressed state persisted. Frontend first frame max-widthnone while next frame remained1440px. Restored Follow Page and verified baseline hash.
- Actual Home visible edges: Header brand and Hero panel both start100.8px at desktopWide; before the native gutter fix Hero started158.8px.
- Actual mobile layout tested in a390px same-origin iframe. Vertical scrollbar reduced layout viewport to380px; document scrollWidth380px, Hero left20px, Header inline padding20px. This is real responsive iframe rendering, not desktop measurements mislabeled as mobile. Raw CDP emulation was unavailable and browser viewport override ineffective; both were excluded from mobile evidence.
- TypeScript passed; final client/SSR build passed (13.42s; pre-existing sample asset and bundle-size warnings). No business schema or API changes.

## Review links

- Home: http://127.0.0.1:8500/admin/pages/144/preview
- All161: http://127.0.0.1:8500/admin/pages/339/preview
- Mobile review harness (local QA only; exclude from releases): http://127.0.0.1:8500/local-unified-width-acceptance-20261115.html

Evidence and raw backups: `output/jw-cms-integration-20261114/unified-width-20261115/`. A future production cleanup must first inventory that environment and establish its own exact document hashes/provenance; local database IDs and QA copies must never be deployed as production content.
