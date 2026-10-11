# CMS Section Surface

Default is the absence of `surface`, not a template snapshot. Existing Section color overrides take priority, missing values inherit the Section/page palette, and without either the existing layout defaults remain. Explicit background classes remain in Default.

Light (`surface: light`) uses palette paper/ink. Contrast (`surface: contrast`) uses accent/light. Both modes apply effective colors only: authored customColors and typography colors remain in the document. Returning to Default restores those retained values.

Section palette overrides page palette. Applying a different palette is a separate intentional action and replaces color overrides as described in the Inspector. Surface switching never performs that action.

Image-based hero layouts retain image/overlay controls and do not offer Surface. Old Sections, manual additions, and templates share the same resolver in Builder and public rendering. Existing explicit Light/Contrast fields retain their meaning; absent fields display Default. No bulk page migration is required.

Previously deleted color data cannot be recovered by this rule; restoring a historical design requires an authoritative prior document and a separately scoped repair.
