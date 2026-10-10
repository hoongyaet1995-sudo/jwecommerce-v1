# Section Inspector layout grouping audit

Scope: source audit only; no UI or data changes. 2026-10-10.

Canonical section contract contains 35 semantic section types; Add Section archetypes are variants and must not be counted as separate control implementations.

Recommended Design order: Section layout -> Typography -> Image appearance (when rendered) -> Layout & spacing -> Motion -> Palette defaults & colors.

Use one conditional Section layout disclosure, with selected-type subtitle and current-layout summary. Default open on first visit; remember employee disclosure choices locally without saving UI state into the page document. No empty group for unsupported types. Selecting a canvas image/text should open its related shared disclosure rather than force the layout group open.

| Type | Current dedicated design settings / grouping boundary |
|---|---|
| `chapter_navigation` | No dedicated layout selector currently found in SectionInspector or its mounted settings modules. Retain applicable shared controls; do not add an empty Section layout group or infer capability from schema fields alone. |
| `chapter_block` | Chapter preset; PolicySettings also appears for Policy preset. One top group, not nested duplicate layout groups. |
| `hero_banner` | No dedicated layout selector currently found in SectionInspector or its mounted settings modules. Retain applicable shared controls; do not add an empty Section layout group or infer capability from schema fields alone. |
| `hero_slider` | Slider preset, height, autoplay and interval. Overlay opacity belongs to Image appearance. |
| `split_hero` | No dedicated layout selector currently found in SectionInspector or its mounted settings modules. Retain applicable shared controls; do not add an empty Section layout group or infer capability from schema fields alone. |
| `announcement_bar` | Ticker speed (presentation behavior). |
| `media_text_split` | Layout preset, desktop media position, CTA presentation. Media width remains shared but may be grouped here as a composition choice. |
| `rich_text` | Default / Manifesto. Split Quote, author and role back into Content before regrouping. |
| `timeline_story` | No dedicated layout selector currently found in SectionInspector or its mounted settings modules. Retain applicable shared controls; do not add an empty Section layout group or infer capability from schema fields alone. |
| `stats_counter` | No dedicated layout selector currently found in SectionInspector or its mounted settings modules. Retain applicable shared controls; do not add an empty Section layout group or infer capability from schema fields alone. |
| `product_grid` | No dedicated layout selector currently found in SectionInspector or its mounted settings modules. Retain applicable shared controls; do not add an empty Section layout group or infer capability from schema fields alone. |
| `product_listing` | Carousel / Editorial Grid, columns, frame ratio, autoplay, interval, arrows. Product sources/limits remain Content. |
| `collection_tabs` | No dedicated layout selector currently found in SectionInspector or its mounted settings modules. Retain applicable shared controls; do not add an empty Section layout group or infer capability from schema fields alone. |
| `featured_deal` | No dedicated layout selector currently found in SectionInspector or its mounted settings modules. Retain applicable shared controls; do not add an empty Section layout group or infer capability from schema fields alone. |
| `pricing_table` | No dedicated layout selector currently found in SectionInspector or its mounted settings modules. Retain applicable shared controls; do not add an empty Section layout group or infer capability from schema fields alone. |
| `media_gallery` | Default / Editorial Montage / Editorial Cards, columns, heading visibility. |
| `video_embed` | No dedicated layout selector currently found in SectionInspector or its mounted settings modules. Retain applicable shared controls; do not add an empty Section layout group or infer capability from schema fields alone. |
| `before_after` | No dedicated layout selector currently found in SectionInspector or its mounted settings modules. Retain applicable shared controls; do not add an empty Section layout group or infer capability from schema fields alone. |
| `instagram_feed` | No dedicated layout selector currently found in SectionInspector or its mounted settings modules. Retain applicable shared controls; do not add an empty Section layout group or infer capability from schema fields alone. |
| `feature_cards` | Default / Editorial Columns / Service Highlights, heading visibility, columns. Legacy Values List conditional controls share the same group. |
| `values_list` | Heading visibility, row spacing and dividers; no alternate-layout picker currently. |
| `icon_list` | No dedicated layout selector currently found in SectionInspector or its mounted settings modules. Retain applicable shared controls; do not add an empty Section layout group or infer capability from schema fields alone. |
| `comparison_matrix` | No dedicated layout selector currently found in SectionInspector or its mounted settings modules. Retain applicable shared controls; do not add an empty Section layout group or infer capability from schema fields alone. |
| `faq_accordion` | Classic / Minimal, initial answer expansion. |
| `contact_form` | No dedicated layout selector currently found in SectionInspector or its mounted settings modules. Retain applicable shared controls; do not add an empty Section layout group or infer capability from schema fields alone. |
| `store_locator` | Address + map / Address only, only location preset exposes this design choice. |
| `testimonials_slider` | No dedicated layout selector currently found in SectionInspector or its mounted settings modules. Retain applicable shared controls; do not add an empty Section layout group or infer capability from schema fields alone. |
| `logo_cloud` | No dedicated layout selector currently found in SectionInspector or its mounted settings modules. Retain applicable shared controls; do not add an empty Section layout group or infer capability from schema fields alone. |
| `cta_strip` | Classic / Image Banner / Editorial Band / Help Panel, image banner height. |
| `newsletter_signup` | No dedicated layout selector currently found in SectionInspector or its mounted settings modules. Retain applicable shared controls; do not add an empty Section layout group or infer capability from schema fields alone. |
| `delivery_table` | No dedicated layout selector currently found in SectionInspector or its mounted settings modules. Retain applicable shared controls; do not add an empty Section layout group or infer capability from schema fields alone. |
| `policy_content` | Policy layouts and heading visibility. |
| `payment_methods` | Cards / List. |
| `shop_navigation` | Image Cards / Collection Banners, columns or banner proportions/height, heading visibility. |
| `promotion_highlights` | Introduction beside / above offers and offer badge visibility. |

## Audit findings

- Dedicated controls are distributed across the initial Design block and groups 0, 3, 13, 15-18; ShippingDesignSettings separately mounts FAQ choices. Regroup by capability rather than JSX source order.
- RichTextSettings mixes quote content with layout settings. Separate Content/Design ownership first.
- Policy chapter uses ChapterSettings plus PolicySettings. Consolidate both under the same top disclosure, expose subcontrols only for the selected preset.
- Hero overlay opacity duplicates the shared image-design concern; choose Image appearance as its single owner while preserving the saved field and consumer.
- CTA image-banner height select currently contains editorial-band/help-panel options that are layout presets rather than height values. This is a separate existing control/contract mismatch to fix during implementation; this audit does not modify it.
- Several schema types contain columnsCount but do not expose a dedicated columns editor in the current Inspector. Schema availability is not evidence of an offered UI capability.
- Preserve all page fields, save contract, renderer and override priority; regrouping should affect only control placement and local disclosure state.

Code sources: resources/cms/page-contract.v1.json; resources/admin/src/components/pages/SectionInspector.tsx; mounted *Settings.tsx modules. Static source audit, not an all-type browser acceptance claim.
