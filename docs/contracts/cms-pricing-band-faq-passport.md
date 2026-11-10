# Pricing Band & FAQ passport
Owner faq_accordion / pricing-band-faq. Reuses corporate-starter-faq pricing and FAQ family.

| Consumer | Canonical path / control |
|---|---|
| Section heading / intro | title / content, existing text editors and typography roles |
| FAQ heading | subtitle, existing eyebrow role |
| Price facts | existing planContentSource manual/reference modes; amount/currency/period/name/terms fields and source identity selectors unchanged |
| Band plan description | reference: runtime starterPricing.description from pricing source item.description; read-only here. Manual: planDescription, plain textarea only when this preset/manual. Retained hidden through layout and mode switches |
| CTA | buttonText/buttonLink, shared destination policy, incomplete actions hidden |
| FAQ entries | items[].title/description only, true collection add/delete/reorder, stable IDs |
| Accordion | faqInitiallyOpen, default first open, native details/summary keyboard behaviour |
| Layout | horizontal 3-part band + terms below; two-column FAQ; stack on small container; shared page width/gutters/motion |

No images, icons, badges, pricing operations or new global controls. No JW content in generic defaults. Old pricing-card renderer/data unchanged; description projection is optional and only new band consumes it. Existing price/term values never copied or edited at consumer. Manual planDescription belongs only faq_accordion for safe layout retention, not universal schema. Design exposes only actual text/background controls; fixed gradient decoration has no knobs. Planner eligibility unclaimed.
