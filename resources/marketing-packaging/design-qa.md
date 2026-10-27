# Music and brand outro integration QA

final result: passed

Target: approved 8579 music/outro sample. Implementation: authenticated JTG Step 5 workbench, completed film `01a15ddf-c5c0-7106-bc8b-536ff0d4ede7`.

Both captures use 1280 × 720, the music tab, calm track selected, 18% volume, 3-second minimal outro. Viewed the combined `UI-comparison.jpg`, with the approved source and implementation in one comparison input. Dynamic video frames and truthful production copy differ deliberately; the surrounding authenticated workbench is not being redesigned.

Earlier findings and corrections:

- Inherited workbench typography changed the sample root size/line-height. Isolated root now uses the sample 13px/1.6 and font/color values.
- Production CTA copy made the button 12px narrower. The desktop minimum width now preserves the measured sample geometry.
- The final comparison measured 17 selector groups: dialog/header/title/body/preview/badge/tabs/icons/section title/track rows/play control/track title/recommendation badge/footer/primary action. Font size, weight, family, line-height, alignment, colors, background, padding, gaps, radius, width, height and x/y all match. Final differences array is empty.

Responsive check: actual 390 × 844 viewport, document scroll width 390, dialog x12/width366.4, visible footer and reachable primary action; no horizontal overflow. Controls and editor content scroll within the dialog. `live-mobile.jpg` records this state.

Functional evidence:

- Workbench adoption created a separate real 16.291667-second H264/AAC film: preserved 13.291667-second content + 3-second native branded card. Full browser playback reached the end without a media error. Original content hash is unchanged.
- Three native template outputs inspected: minimal, CTA and product background. Logo, company name and CTA remain readable and inside the frame.
- Music audition played the actual 24-second private stream. Local render uses 18% music, real speech RMS ducking and fades; deterministic mixing test confirms 0.25 duck ratio. No clipped PCM peak. Final decode and 391-frame checks pass.
- One real AI request suggested 12% music and preserved the 3-second brand card. The action opened this same dialog with 12% prefilled. Discussion did not generate a film. Cancel discarded the draft.
- 54 backend tests / 327 assertions, 3 JavaScript interaction tests, production UI build and deterministic audio checks pass. Private authorized audio upload was transcoded and tested with consent/ownership checks.

Evidence in the user output folder: `UI-comparison.jpg`, `fidelity-metrics-final.json`, `live-music-final.jpg`, `live-mobile.jpg`, `ai-suggestion-prefill.jpg`, `AI-real-acceptance.json`, `JTG-实际制作验收.json`, actual MP4 and native template PNGs.

Known scope: internal synthesized acceptance music is explicitly labelled. A commercial music catalogue is not supplied; private authorized uploads are supported. Instant dialog audition is a draft preview; exported film is the authoritative actual mix. Packaging from a packaged version replaces existing packaging instead of stacking it. Original films are retained. The new version awaits the user's quality approval and is not published.

No open P0/P1/P2 visual differences. No deployment or release performed.


## Free music library extension — 2026-10-21

final result: passed

User approved curated free tracks and file-only merchant uploads while preserving the existing dialog. New filter controls/track content and the simpler upload disclosure are deliberate functional additions. Latest film01a15e05-3622-7306-abb9-fcd04c4dd519 uses CC0 Revelation12%, minimal outro3s;391frames/16.291667s. Original13.29s content digest unchanged; unapproved/unpublished.

Compared approved sample and updated live music tab together in frame-style-comparison.jpg at1280x720. Original source.css equals the approved sample after newline normalization. The12 unchanged frame/header/title/body/preview/badge/tab/icon/footer/CTA selector groups have zero differences in geometry, typography, spacing, alignment and color (core-fidelity.json). Captured both desktop default787x668 and mobile390x844; mobile document width390 with no horizontal overflow and footer inside viewport. Newly added music filters stack on mobile and editor contents scroll.

Actual browser acceptance:12 library tracks available plus selected legacy internal track for old recipes; technology filter shows2; individual free and uploaded audio play. File-only MP3 upload succeeds and auto-selects, preserves4s outro and17% draft; no source/note/consent required. Current card returns3s/12% before actual free-track render. Actual film played to the end with no media error. A real AI reply recommends Mirror Lake from catalogue tags; suggestion action prefilled that track at12%/3s, cancelled without rendering.

56 backend tests/375 assertions;5 JavaScript interaction tests; UI build and deterministic mixer check pass. Review fixes verified RED-to-GREEN: valid selected upload retained beyond30 and canonical upload track returned in POST without a second config request. No open critical or important review findings.

Evidence: outputs/free-music-library contains live UI/upload/AI/mobile captures, combined comparison, core metrics, curve provenance list, actual MP4 and JSON production/AI receipts. The original music-brand-integration evidence remains the historical initial integration record.
