# CMS Studio ZIP 25 extraction and acceptance

## Boundary and source

Archive: private local `C:/Users/User/Downloads/jw-ecommerce-dashboard (25).zip`.
SHA256: `B72D88557BF1934FCC71328F64493FD0B30328E03B747E3AF8B797549C1B9856`.

Selected source:

| Source | SHA256 | Target / role |
| --- | --- | --- |
| src/App.tsx | 36A396E2D778F9D9392C02AF947C56D19CE4D6A3E121281BE96B5BD9A7423925 | Admin layout conditional shell and offsets |
| src/components/pages/PageAdmin.tsx | A5C0C80513A918A17BB2D0AFBE0BF0A3F5F176AB8C37CCA944A911A14F798FB7 | Editor root, toolbar, Exit, Studio badge, theme toggle, desktop canvas width |

Raw extraction is outside repository in the local Temp directory. Source started using existing compatible Admin dependencies and Vite at 127.0.0.1:8515. No other source modules imported. Existing AdminContext selected-page state and theme actions are compatible dependencies; current renderer, inspector, autosave, publish, import/export and authenticated APIs remain the existing adapters. No backend code or contract changes for this feature.

Preview: `http://127.0.0.1:8500/admin/pages?cmsStudioPreview=25`. Click Edit Layout. Query-selected module flag isolates the converted layout from ordinary Admin routes. This uses real saved page data, not the ZIP's mock page records.

Official cutover: user approved "确认替换正式入口". The query gate and legacy layout branches were removed; /admin/pages now uses the approved Studio layout. Earlier logo work remains in the same dirty worktree and is not deployed.

## Evidence

Screenshots under repository `.playwright-cli/` (local acceptance output):

- Source desktop 1440x900: page-2026-09-05T04-42-09-272Z.png
- Source phone 390x844: page-2026-09-05T04-40-43-735Z.png
- Converted desktop 1440x900: page-2026-09-05T04-43-36-396Z.png

Browser confirms editor rectangle x=0, y=0, width=1440, height=900 and Admin header/sidebar absent. Full Canvas and Mobile controls produce 390px canvas. Theme control successfully changes mode. Existing title/page content differ because preview consumes persisted customer pages. The existing Publish Changes action remains when a published page has an unpublished draft; it occupies additional toolbar width compared with the source mock. Saving and navigation continue through existing autosave guards.

Source limitation: at an actual 390px screen, toolbar text overlaps and the two panels obscure the canvas. The ZIP's 390px Mobile control is a preview within a desktop editor, not a phone-optimized editor workspace. Source limitation retained and disclosed; no mobile redesign invented.

Checks: Admin tests 342 passed; PageDraftApiTest 26 passed / 211 assertions; TypeScript and Vite build passed (existing large-chunk warning). Backend drafts, publishing, private media, permission checks and canonical data are not replaced by ZIP mocks.

Final interaction checks: Full Canvas measured desktop 1280px, tablet 768px, mobile 390px. Exit to Pages removed the editor and restored both Admin header and sidebar. No page content or publication status was changed by browser acceptance.

Cutover verification: ordinary /admin/pages (no query parameter), Edit Layout -> editor x=0/y=0/1440x900, header and sidebar absent. TypeScript and production build passed. No GitHub or Hostinger release performed in this phase.
