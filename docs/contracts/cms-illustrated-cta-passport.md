# Illustrated CTA Panel passport
Owner: cta_strip / illustrated-panel. Reuse shared SaasClosing renderer, text roles, image upload/ScopedImage and action URL policy.

| Consumer | Canonical fields and controls |
|---|---|
| Heading/body | title/content, existing content editors and title/content typography roles |
| Foreground illustration | mediaUrl + imageSlots[target=mediaUrl].mediaAlt, one shared image target with locked contain geometry, canonical managed-media URL/alt; no video or crop/overlay knobs |
| One CTA | buttonText/buttonLink, independent editable label/destination; invalid or incomplete destination hides action; editing prevents navigation |
| Surface | page-aligned container, spacing/motion shared; fixed light blue-violet panel gradient/radius/shadow, editable title/content colors only; outer background uses customBgColor |

No subtitle, secondary action, icon/badge/items or product data editors. Hidden legacy fields/media retained on safe layout switches. Generic starter uses neutral copy and empty image/link; JW artwork/copy belongs to draft data. Desktop image left/copy right, phone stacked and image contained; shared Content width/gutters. No new schema fields, only bounded ctaPreset value. No Planner eligibility claim or commerce/backend side effects.
