# Unified Section Inspector

Local implementation approved 2026-10-08. The global catalogue currently contains 38 presets across 30 section types. All use the same Inspector; specialized controls remain owned by their section type and preset.

| Category | Responsibility |
| --- | --- |
| Content | Text, introduction, entries, images and alternative text, actions and links, catalogue sources, form content and destinations |
| Design | Layout preset, typography, image appearance, spacing, borders, colors, motion and carousel presentation |
| Advanced | Explicit container exceptions for supported cover/background layouts; page width remains in Page Settings |

Existing controls are retained in 27 categorized groups. Switching tabs keeps control components mounted, preserving upload and coupon state. Layout, motion and palette controls are collapsed initially. Image appearance only appears when the section has editable image targets. Content owns asset replacement and alternative text; Design owns ratio, position, fill, width, shape, overlay and foreground. Both share the same selected target and canonical image-slot updater.

## Text styles

`typography` is an optional section field on all types, validated by the shared document contract. Supported roles: `eyebrow`, `title`, `lead`, `content`, `itemTitle`, `itemContent`. The Inspector offers only roles rendered by the current layout. For example, montage photographs have no visible item captions, and a Principles chapter has no main headline.

Each role can override font family, size, weight, six-digit color, line height and letter spacing. Size is 8–120 px, weight is 400–900 in hundreds, line height is 0.8–3.0 in tenths, and letter spacing is -5–10 px in half steps. Fonts are inherited, sans serif, serif or monospace. Numeric and hex fields commit complete valid values on blur or Enter. Reset removes the role override and restores the existing preset/legacy palette behavior. No override changes existing rendering.

Item styles apply consistently to all entries in a section. Item copy and image settings remain independent. Existing arbitrary legacy palette strings are retained in the collapsed palette controls; new text overrides accept validated colors only.

## Canvas selection

Text nodes expose semantic `data-cms-text` targets and stable item IDs. Clicking a text element selects its section, opens Content, scrolls to its field or item, and highlights the control. Style opens Design for the corresponding text role. Clicking an image selects its canonical image target; Style opens that image’s appearance controls. Product cards select their catalogue source; form elements select their form controls. Builder interaction markers and hover outlines do not enable editing commerce data in the public storefront.

## Adding sections

1. Register the section in the shared document contract and global catalogue.
2. Reuse the shared page container, typography helper and image-target registry.
3. Place content and presentation controls in their corresponding category, preserving canonical storage and existing capabilities.
4. Mark rendered editable text and stable item IDs for canvas selection; avoid markers on computed product data.
5. Extend the all-preset Inspector/rendering coverage tests. They check three categories, retained control groups, shared containers and that offered text roles actually render.

Verification is recorded in `docs/acceptance/2026-10-08-page-template-pilot.md`. Local work does not authorize publication or synchronization.
