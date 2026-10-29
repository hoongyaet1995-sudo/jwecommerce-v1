# Post template and campaign selector UI sample

- Preview: /post-template-selection-sample/#campaign and #editor.
- Standalone HTML/CSS/JS only; existing business routes, templates, campaign records and permission checks unchanged.
- Reuses current Marketing Studio CSS, icons, font system and Posts editor layout.
- Design proposal: each Post template has a platform and supported format; a Post campaign selects exactly one template. Posting account and schedule are a later review step.
- Platform format choices are constrained to currently implemented workflow routes, not a claim that every platform-native feature is implemented. YouTube shows video only; Facebook/Instagram image and carousel; TikTok photo and video.
- Existing product files are visual sample assets. Video cards use labelled poster placeholders, not newly generated videos.
- Sample state is confined to localStorage jw-post-template-sample-v1. No backend save or AI/provider request exists.
- Verified in normal Chrome: chooser labels include platform and format, selecting a second card clears first selection, applying a single template shows platform/format in campaign, YouTube hides image formats, caption retained on platform switch.
- Visual QA corrected shared .preview-media style collision and made chooser footer fixed within dialog grid. JS syntax check passed.
- Formal integration pending user design approval. No commit/push/deployment.

## Approved implementation correction
- User approved Campaign selector only; rejected separate sample editor. Formal Create Template > Post (and legacy edit Post template entries) now navigates to existing Posts list. Original Posts editor is reused without a duplicate editor.
- Campaign attaches a single actual saved Post draft; legacy template records remain preserved. Server rejects two or more IDs and leaves prior choice intact.
- Original Posts format cards are filtered using existing boot capability matrix. Unconfigured account records are labelled honestly; no sample platforms imported.
- Local 38 tests / 203 assertions passed. Browser verified redirected list and real card dialog. Actual per-platform connected-account browser branch remains subject to local account availability; no fake account was installed.
