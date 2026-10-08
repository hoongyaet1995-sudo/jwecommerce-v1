# About Us — Studio

The approved second visual concept is a separate page template (`about-studio`), following the original About Us and About Us — Editorial entries. Existing pages and the site shell remain independently configured.

Six sections use owned CMS capabilities:

| Section | Type / preset | Catalog category |
| --- | --- | --- |
| Intro and staggered photographs | media_gallery / editorial-montage | Header & Intro |
| Brand perspective and chair photograph | media_text_split / editorial-story | Storytelling & Narrative |
| Two brand ideas | feature_cards / editorial-columns | Storytelling & Narrative |
| Three photograph captions | media_gallery / editorial-cards | Media & Visuals |
| Three brand values | feature_cards / editorial-columns | Storytelling & Narrative |
| Dark photograph CTA | cta_strip / image-banner | Conversion & Leads |

All layouts use PageSectionContainer. The CTA uses Full bleed; its text follows page gutters. Gallery images use separate stable item IDs and shared Image Tools. Inspector supports preset selection, optional headings, columns, add/delete/reorder items, content, theme, colors, spacing and motion. Mobile stacks entries in saved order. Gallery/item defaults are cloned on insertion.

Eight generated illustrative photographs are bundled in public/page-template-sample/studio. `AboutStudioMediaSeeder` installs them in managed storage with IDs declared by resources/cms/about-studio-media.json. The frontend mapping must match that manifest. Before accepting a future deployment, include the bundled originals and run `php artisan db:seed --class=AboutStudioMediaSeeder --force` in the approved environment, then verify all eight managed URLs and saved Preview. This document grants no deployment authorization. Keep the managed assets available for the registered template, even when deleting a sample draft.

Photos reproduce the art direction and subjects of the supplied visual; they are newly generated assets, not original extracted photographs. Store name is initialized from saved settings; other sample copy remains editable. Header/Footer continue to use the current global site configuration.
