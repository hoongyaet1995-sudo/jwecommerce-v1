# Product PDP Content Admin and Database Design

## Goal

Persist the Google AI Studio Product Editor's PDP Rich Content & Specs and SEO
tabs through the protected Admin catalog API into MySQL. This phase does not
change any public PDP, PLP, or storefront runtime renderer.

## Source and UI Boundary

- Source ZIP: `jw-ecommerce-dashboard (9).zip`
- SHA-256: `CE01F1E751228DA4EE0DE34F078CDC8A8615E992339EEBFBEA07160241D45EE2`
- Selected source components: `ProductEditorModal.tsx` and
  `ProductExtraInfoEditor.tsx`.
- Preserve the source Tab 4 and Tab 5 DOM, classes, copy, local interactions,
  responsive behavior, template buttons, and sub-section navigation. Existing
  Tabs 1–3 are not visually redesigned or reordered.

## Runtime Data Model

### Product PDP content

`product_pdp_contents` is a one-to-one extension of `products` and contains:

- `highlights`: ordered JSON items `{id, text, badge?}`.
- `specifications`: ordered JSON items `{id, group?, label, value}`.
- `use_store_default_shipping`: boolean.
- `shipping_policy`: nullable JSON override with title, badge, lead-time, and
  policy body. It is null when the product inherits the global policy.
- `custom_accordions`: ordered JSON items `{id, title, icon?, content,
  defaultOpen?}`.
- `rating_score`, `review_count`, and `configurator_label` scalar fields.

### Related products

`product_pdp_related_products` has a source `product_id`, `related_product_id`,
and sort order. Database uniqueness prevents duplicates; validation rejects a
self-link, archived product, and more than four related products.

### Curated reviews

`product_pdp_reviews` stores individually editable curated review records.
They are explicitly operational seed/social-proof content in this phase, not
customer-order reviews. They carry author, rating, date, title, content,
variant label, verification flag, helpful count, and sort order.

### Global shipping fallback

`store_settings.shipping_policy` is the canonical all-store fallback. Product
records with `use_store_default_shipping=true` do not duplicate it. This phase
does not add a new global policy editing screen; it reads an existing value or a
safe server fallback when absent.

## API Contract

The protected `/admin/api/catalog` product object gains `pdpContent`,
`crossSellProductIds`, and `reviews`. Create and update are atomic: product
fields, related products, content, and reviews all persist or none do. Laravel
validation returns stable 422 field paths. The API only returns safe catalog
data for related-product selection.

## Behavior

- Template actions update only local editor state until Save Product & SKUs.
- Deleting a highlight, spec, accordion, review, or relationship uses the
  existing central deletion-confirmation flow.
- A refresh/relogin returns precisely the saved admin state.
- No new public store payload, Blade renderer, route, or front-end purchase
  behavior is introduced in this phase.

## Tests and Acceptance

1. Feature/API tests cover authorization, validation, relationship integrity,
   global-vs-custom shipping, review constraints, transaction rollback, and
   refresh equality.
2. Admin React tests cover source-faithful tab navigation, editing, template
   state, save/error state, and reload hydration.
3. Authenticated browser proof covers desktop and mobile tabs, save, reload,
   destructive confirmation, and the absence of public-renderer changes.
