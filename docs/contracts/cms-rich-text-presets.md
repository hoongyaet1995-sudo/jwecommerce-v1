# CMS rich_text presets

Scope: one globally reusable rich_text capability; no page ID, slug, category or About Us template condition. The user-approved reference is the supplied first About Us sample, centered Every detail has a story statement.

## Capability passport

| Canonical path | English Inspector | Consumer and default |
| --- | --- | --- |
| richTextPreset | Layout preset: Editorial Text & Quote / Manifesto | Absent/default preserves original rich_text DOM/classes; manifesto uses shared Manifesto component |
| subtitle | Subtitle / Eyebrow Tag | Optional centered uppercase eyebrow |
| title | Manifesto headline (multiline) | Responsive heading, preserved line breaks and long-word wrapping |
| content | Editorial Description | Manifesto splits blank lines into paragraphs and preserves single line breaks; body max720px |
| paddingY | Vertical Spacing: Compact / Normal / Spacious | Manifesto responsive vertical space32/40,48/64,64/96px respectively |
| backgroundColor, customColors | Existing Background Canvas Style / Brand Palette | Existing section theme; explicit text colors retained; missing text colors derive readable fallback from current background/custom Hex luminance |
| quote, authorName, authorRole | Quote / Quote author / Author role, original layout only | Original blockquote consumer; preserved when switching to Manifesto and hidden there |
| textAlignment | Existing alignment, original layout only | Manifesto owns centered alignment; stored original value retained |
| motionPreset, isVisible | Existing motion / visibility | Shared CMS motion and visibility path |

No images, CTA, author avatar, items, forms or generic arrays are introduced. Existing stored authorAvatar is retained but remains hidden because the rich_text renderer does not consume it. Fixed reading geometry uses outer max960px, inner responsive padding24/40px and body max720px. Body text is16px, heading30/36px. Manifesto has no card, quote box, image, divider, or shadow of its own. Existing section-theme borders are unchanged.

## Persistence and compatibility

Only rich_text manifest accepts richTextPreset, a bounded default|manifesto enum. Both Laravel contract validation and browser bootstrap reject wrong values/owners. No schema migration or normalization of old documents occurs. The canonical draft API/MySQL document owns persistence; Builder and saved public/preview use SectionRenderer and the same Manifesto component. Switching presets changes presentation, not quote/content/custom-theme data.

This is source-directed local About Us sample work. Existing header/footer configuration stays separate. Sample text is illustrative, not verified company facts. No new AI Planner eligibility is claimed. Values List and Image Banner remain separate later section tasks. Local screenshot/persistence/mobile evidence is recorded in docs/acceptance/2026-10-08-page-template-pilot.md. No publish/home switch/commit/push/deploy without the later release authorization.
