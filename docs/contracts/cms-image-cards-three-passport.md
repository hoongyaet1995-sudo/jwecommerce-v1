# Media Gallery — Image Cards Three passport

Scope: only `media_gallery/galleryPreset=image-cards-three`. Local design integration, not Planner/publication readiness. Reuse canonical item/image/text/container contracts; no business purchase-mode operations.

| Capability | Canonical data / consumer | Inspector boundary |
| --- | --- | --- |
| Heading | title/content; shared gallery Heading | Existing heading and body editors; uniform role colours |
| Three fixed display slots | items[0..2], stable ids | Exactly3 visible; no Add/Delete/Reorder; blanks remain editable |
| Card content | items[].title/description | Existing text fields, title/body text roles |
| Image | items[].imageUrl + imageSlots per stable target | Existing shared image target/upload/alt controls, no duplicate editor |
| Presentation | existing paddingY, customColors background/title/body, heading alignment | Shared supported Design fields only; no columns, media width, button/eyebrow colours |
| Actions/icons | None | No links, CTA, icon, number, badge or nested diagram controls |

Renderer and Inspector both materialize3 empty slots for sparse data with deterministic section-scoped ids. Other-layout entries beyond the first3 and unsupported item properties remain stored but hidden in this layout for safe A/B/A; never destructively truncate them or expose a fourth card. New starters contain exactly3 entries and neutral copy/empty media, no JW page/brand/action dependencies.

Complex photographic/UI/number/mini-journey details are flattened into each of3 replaceable images. Actual titles/descriptions stay outside artwork. This is a generic presentation family, not real product/cart/quotation widgets. Number or layout-span fields are not registered. No new universal schema fields.

Evidence gate: sparse and legacy render/control tests, canonical preset ownership, real Builder image/text edits/save/reopen, A/B/A, starter discovery/insertion and desktop/mobile. Planner eligibility remains unclaimed.

Local evidence 2026-11-10: `docs/planning/corporate-capabilities/acceptance/local-ecommerce-three-cards.md`. Draft63 has the accepted page data; generic starter remains independent and neutral. Ready for user review, not published/deployed.

2026-11-10 approved tablet density: only600–900px container. Three-card preset retains3columns with smaller type/padding; four-mosaic becomes2equalcolumns/220–300px contained image frames. Counts/data/media/control ownership unchanged; phone/desktop unaffected.
