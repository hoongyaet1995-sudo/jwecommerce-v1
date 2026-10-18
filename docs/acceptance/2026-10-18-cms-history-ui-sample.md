# CMS Version History — UI sample, 2026-10-18
Scope: standalone design only. No Laravel API, migration, CMS document write, provider request or deployment. Source-faithful family uses existing CMS dark/indigo admin styling; editorial preview uses existing logistics media. Files public/page-template-sample/cms-version-history.{html,css,js}. URL /page-template-sample/cms-version-history.html.

Screen responsibilities:
1. Entry/editor: History beside Preview replaces Inspector with history panel. Editor structure remains left Outline + canvas + right panel.
2. History: date/actor/source, current draft/live badges, AI Apply badge, named checkpoints, expandable autosave group, Versions/Changes tabs, read-only preview CTA. Mock version records only; v12 owns the fully illustrated preview.
3. Preview: read-only banner and version/date/actor, back to current draft, comparison link, restore to draft. Inherited dynamic data disclaimer.
4. Compare: selected v12 versus current v14 canvases, field-change cards; restore remains explicit. No arbitrary pair comparison/partial restore.
5. Restore: modal states new draft creation, current preservation, later history preserved, live stays v13, shared business/theme data outside scope. Keyboard escape/tab behavior. No AI call.
6. Result: mock new v15 marked current; v14 previous draft, live v13 unchanged. Named checkpoint input retained only in sample memory.
Additional More states: empty baseline, concurrent update blocked; sample Reload action only resets demo UI. Bottom sample navigation not part of production UI.

Acceptance: JS syntax node --check passes. Real IAB interactions preview/compare/restore/result/named checkpoint/empty/conflict verified. Late render overflow corrected via grid child min-height0 and independent timeline scrolling; actions pinned visible. Desktop screenshot01-history,02-compare,03-restore; mobile04-mobile under output/cms-version-history-sample. Mobile390x844 document width390; dialog left20 right370.4. Browser viewport reset. No backend versioning claimed. Sample only, no formal feature integration. Earlier Generator closure source remains unrelated and uncommitted.
