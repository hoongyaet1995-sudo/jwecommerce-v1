# JTG Solution Detail and Media Gallery — visual review

Status: design samples, not registered/published templates. English customer-facing copy; existing global JTG header/footer, Page Width, Barlow/Inter font registry and main/sub color tokens remain authoritative. Generated image copy is illustrative; canonical CMS copy will use 2026 and approved wording.

## Navigation

- Homepage Site storage card -> independent Site storage solution page.
- Homepage Delivery planning card -> another page using the same detail template and independently editable content.
- Detail gallery action -> independent Media Gallery page; photo selection -> keyboard-accessible lightbox with caption, previous/next and close.
- Related product links -> real selected product PDPs. Quote CTA uses the existing appropriate product enquiry entry, or general support when no product is selected. Do not submit a fabricated product enquiry from a content-only page.
- Current Our Projects label can retain the user-selected header menu text. Content describes applications/solution concepts until documented real project facts are supplied; no invented completion dates, locations, customers or results.

## Control and layout mapping

| Visual region | Existing semantic section | Reuse / required extension | Editable fields |
| --- | --- | --- | --- |
| Hero photograph and overlay | hero_banner | Reuse industrial-overlay | Image/alt, eyebrow, title, content, actions, overlay, existing text roles |
| Split heading and introduction | rich_text | Add industrial-split layout if the existing text layouts cannot preserve the sample | Title, content, alignment, typography |
| Three considerations | feature_cards | Reuse service-highlights; use shared border/design tokens | Repeated icon/title/description; Add/Delete/Reorder |
| One large + two small gallery images | media_gallery | New industrial-mosaic layout on existing type; current montage has different proportions and is not a faithful substitute | Image, alt/caption/title, order and gallery action |
| Navy heading + vertical numbered list | feature_cards | New industrial-process-split layout on existing type; existing industrial-process is horizontal | Title/intro and repeated title/description; automatic 01/02 numbering |
| Related actual containers | product_tabs | Existing real-product selector/data source; add compact horizontal card presentation only if required for fidelity | Selected product IDs/order; titles/images from actual catalog, no copied dummy pricing |
| Closing enquiry/support panel | cta_strip | Shared CTA fields; outlined industrial layout if existing presets do not render this geometry | Title, note, primary/secondary actions |
| Independent gallery browser | media_gallery | New album-browser layout with group filtering, incremental reveal and lightbox; no new unrelated Section type | Existing image/caption/alt + optional per-image group; controls below |

## Gallery-specific additions

- Content: per-image **Image group** text/value, separate from Product Tags. Tabs derive from authored groups, plus All images. No fixed industry categories hidden in code.
- Design > Section layout: Industrial Mosaic / Album Browser visual choice cards. Only rendered capabilities appear in Inspector. Reuse existing heading, text roles, image target, Page Width and spacing controls.
- Album Browser controls: group filters on/off, initial image count, load-more count, lightbox on/off. Count reflects authored/filtered images. Do not imply server pagination if all CMS images are already in the document; use lazy image loading plus incremental reveal.
- Lightbox reads the same authored images and captions; no external album service. Mobile single/two-column layout as space permits, no horizontal overflow, close/previous/next labels and focus restoration.
- All repeated fields must survive canonical save/refresh and layout A -> B -> A; sparse/empty galleries must still expose Add Image and supported controls. Preserve hidden authored data when switching layouts.

## Source and business boundaries

All visuals are AI-generated design samples/illustrations, not proof of delivered JTG projects. Existing store/catalog data supplies real product links after implementation. No new service/stock/transport guarantees. Header/footer stay global; backgrounds can be full width, all foreground copy and gallery grids obey Page Width. New layouts reuse semantic types and shared Inspector; no per-page hardcoded renderer.

## Review / implementation boundary

Visual approval comes before CMS implementation. Afterwards verify draft starter, Inspector fields, Add/Delete/Reorder, canonical validation, save/refresh, public rendering, desktop/mobile and link/lightbox actions. No publishing or production synchronization is included in design approval.
