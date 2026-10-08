# Shared media_text_split presets

Approved 2026-10-08: presets and Inspector capabilities belong to the global CMS section type, not an About Us template. Content, images, colors and selected presets remain independently owned by each section instance. Templates only compose sections and supply initial values.

## Approved scope

- `default`: original renderer DOM/classes unchanged; no automatic migration.
- `editorial-hero`: existing first sample hero, mobile text first, desktop image position selectable, fixed desktop image height620; first visible section can render H1.
- `editorial-story`: new opt-in H2 narrative preset; desktop approximately55% image/45% text, selectable image side, mobile image first, straight image edges/no shadow; bounded4:3/16:9/1:1/original ratios; paragraph breaks entered as a blank line, rendered as escaped independent paragraphs.
- Optional primary CTA for both editorial presets: `buttonStyle` is `button` or `text-link`; empty text or target hides it. The default is button. No sample/fallback links.
- Both editorial presets share image alt/focal, headline newline, color/spacing/motion/visibility controls. Ratio control appears only on Story because Hero fixes image geometry. Hide ineffective text alignment. Selecting a preset initializes missing editable fields and preserves authored values; selecting Classic only changes layoutPreset.

## Ownership and persistence

The shared version1 manifest validates optional layoutPreset and buttonStyle enums, and allows these fields only on media_text_split. The browser bootstrap classifies both fields explicitly. Canonical draft API/MySQL JSON owns persisted section values; the shared SectionRenderer serves editor and saved preview/public paths. Unknown fields and invalid values fail explicitly. Existing missing fields remain missing unless the operator explicitly selects a preset.

No page ID, template name, About Us slug, route or page category may decide preset availability or renderer behavior. New Inspector grouping uses existing controls/styles; this scope does not redesign other Section types or global navigation.

## Acceptance boundary

## Shared Image inspector capability (2026-10-08)

The reusable `SectionImageSettings` and contextual Image tools are connected to all ten authored-image section families listed below, including Classic, Editorial Hero and Editorial Story. Availability is independent of page/template identity. Layout-side selection stays separate from image crop position; duplicate ratio/focal controls are removed from the preset panel, while stored legacy values remain default fallbacks.

| Canonical fields | Inspector | Renderer/default |
| --- | --- | --- |
| mediaUrl / mediaAlt | Existing URL/upload picker, preview, alternative text | Managed/external image; no new placeholder fallback |
| imageRatio | Default, original,16:9,3:2,4:3,1:1,3:4 | Default retains Hero square-mobile/620px-desktop and Story mediaAspectRatio; explicit ratio overrides geometry |
| imagePosition | Nine-point grid | object-position; missing retains legacy mediaFocalPoint |
| imageFit | Cover / Contain | object-fit, Cover default |
| imageWidth | Advanced:100/75/50 percent | Percentage of image column, centered; never changes section columns |
| imageShape | Advanced:default/rectangle/rounded/circle | Default legacy straight edges; rounded corners or square circular clipping |
| imageOverlay / imageOverlayOpacity | Advanced:dark overlay toggle/opacity | Independent black image layer; off by default,30% when enabled |
| imageForeground / imageForegroundColor / imageForegroundOpacity | Advanced:color layer toggle/picker/Hex/opacity | Independent image tint layer above overlay; off by default,indigo20% when enabled |

Opacity is a bounded0..100 enum in10-point steps. Legacy flat image fields are optional on media_text_split; other image targets use bounded imageSlots permitted by their family manifest; browser bootstrap validates matching enums/types. Both decorative layers are aria-hidden and do not intercept clicks. Advanced settings are initially collapsed. Foreground is a JW-defined color tint capability; Relume's full Foreground behavior was not established during reference research. This does not introduce foreground text or change the section CTA.

Upload continues through the existing native file-selection dialog and Laravel managed upload service. No new media-library modal/API is claimed. Browser upload permission remains an explicitly recorded environment limitation.

Use About Us sample page29's SECOND section, preserving its accepted hero and other sections. Verify a separate non-About QA draft can add the same section, select/edit/save/reopen the same presets. Keep both unpublished; preserve the independently changed Shipping homepage. Compare actual desktop/mobile shared renderer and Inspector behavior, persistence/validation, existing Hero and Classic regression. No commit/push/deploy until later sync approval.

Illustrative sample images/copy are not verified company facts. Chrome automatic upload permission remains unavailable; do not retry or change extension permissions. Use the existing local ManagedMediaIngest service for sample assets; do not claim browser upload acceptance.


## Contextual multi-image integration (2026-10-08)

The Inspector displays one Image tools panel. Single-image sections select their image automatically. Multi-image sections expose labeled thumbnails; clicking the canvas image/card or a thumbnail selects one target. The active target is transient editor state. All new controls, labels and helper text are English.

| Family | Canonical image targets | Editable geometry |
| --- | --- | --- |
| media_text_split (Classic/Hero/Story) | mediaUrl | Ratio, width, shape |
| split_hero, featured_deal | mediaUrl | Ratio, width, shape |
| hero_banner | mediaUrl | Layout owns dimensions |
| hero_slider | item:{item.id}:imageUrl | Banner layout owns dimensions; selected thumbnail activates slide |
| media_gallery, instagram_feed | item:{item.id}:imageUrl | Ratio, width, shape |
| before_after | beforeImageUrl, afterImageUrl | Comparison frame owns dimensions |
| testimonials_slider | item:{item.id}:authorAvatar | Avatar layout owns dimensions |
| logo_cloud | item:{item.id}:imageUrl | Logo layout owns dimensions |

Crop position, fill mode, alt text, overlay and foreground tint remain editable for constrained images. Geometry controls are visibly disabled with an English explanation; PHP and public bootstrap reject incompatible overrides. Editor slider autoplay pauses while editing, public autoplay remains configured as before.

Per-image overrides are stored in section.imageSlots with a unique target string and only the bounded shared image fields. IDs follow the underlying item, preserving settings after reordering. Removing an item prunes its image slot. Upload completion resolves the stable target against the latest section before applying the URL. Existing media_text_split flat fields remain canonical for normal edits and legacy values remain fallbacks. Imported nested main-slot geometry is consumed consistently.

Catalog product photos remain owned by catalog data; videos and icons retain their dedicated controls. Non-image section renderers do not expose unsupported image settings. Obsolete card-photo controls for feature_cards/timeline_story/store_locator were removed because their renderers do not consume those photos; no extra visual design was introduced. This phase changes image capability, not the remaining About Us section categories or public status. No AI Planner eligibility is claimed.
