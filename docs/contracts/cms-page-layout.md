# CMS Page Layout

Page Settings owns the document `containerWidth`: Standard (1280px) or Wide (1440px), including responsive horizontal gutters of 24/40/64px. Missing settings retain Standard. Settings are stored in the versioned page document and shared by Builder and public rendering.

Every section renders through `PageSectionContainer` in `SectionRenderer`. New section types inherit this wrapper automatically. Section renderers must not add their own outer max-width, horizontal padding or page margins. Internal reading widths, card dimensions, column proportions and banner copy insets belong to the layout preset and may remain constrained.

Background-capable sections expose collapsed Advanced Layout with Use page layout or Full bleed. Full bleed removes the outer width constraint; hero/banner text must still follow the shared page width and gutters. Ordinary sections do not expose width controls.

Validate new layouts in both Builder and saved-page Preview at desktop and mobile widths. The runtime catalog test covers every registered template using the shared wrapper; visual acceptance must also check actual content edges and overflow.
