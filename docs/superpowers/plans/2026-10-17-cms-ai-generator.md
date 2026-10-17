# CMS AI Generator Implementation Plan

Goal: connect the approved five-screen flow to real AI planning, bounded editable CMS content and existing draft persistence; add real images only after the text path is verified.
Architecture: protected Laravel controller delegates to a builder service; AI returns bounded JSON, never executable HTML or arbitrary media/links. React modal reuses SectionRenderer and PageAdmin draft/autosave. No publication or deployment.
Spec: docs/designs/2026-10-17-cms-ai-generator-functional-spec.md.
Execution: inline, in the canonical development root as explicitly required by user; preserve existing dirty files. No commit until synchronization approval. Timebox requested: 15 minutes, starting 05:18:51 UTC.

- [ ] Add failing service tests for unsafe AI output, scope preservation, invalid response and Brief binding; record intentional red separately.
- [ ] Implement CmsAiGeneratorService and protected brief/generate/refine endpoints. Preserve fields outside bounded text changes; validate final canonical document. Cache user-bound Brief token for confirmation.
- [ ] Add five-screen AiPageGenerator component and toolbar entry. Reuse renderer, actual API and existing draft save. Show explicit limitations for capabilities not connected.
- [ ] Run focused PHP tests, frontend build, and authenticated localhost browser verification. Record actual outcomes and partial completion at timebox.
- [ ] If time permits, implement image provider + managed media ingest and focused tests; otherwise leave generation controls visibly unavailable and report this gate open.

Review focus: unauthorized requests, AI output injection, stale/wrong-user Brief, selected-section escaping scope, unpublished draft isolation. No fake progress or successful completion after failed provider calls.

## Ledger
Baseline: existing sample/spec untracked; unrelated dirty work in PageAdmin and many frontend files preserved. No previous implementation-test failures for this phase. Token accounting unavailable.
Intentional TDD red: CmsAiGeneratorTest before service exists; purpose prove missing safe generation behavior. Unexpected failure count remains 0.
TDD red observed: missing service in 3 tests as intended. Now implementing safe text-only patching; run focused tests after service added.
05:27 UTC checkpoint: initial 3 service tests green (8 assertions), Admin build passed. Added endpoint unauthenticated and canonical output checks; running expanded suite. Initial release intentionally text-only; full capability catalog/images remain open.
Timebox exceeded at 05:37 UTC. Browser detected object/list normalization missing at new controller (unexpected issue count 1). Fixed by reusing PageDocumentJsonCodec; cached document equality compares JSON values, not stdClass object identity. Images, dynamic section selection, full persistence and real generation acceptance remain OPEN. No release.
New authorized scope: restore approved five-screen fidelity and implement typed one-question-at-a-time modal (text/select/radio/multiple_select), retained answers and AI Brief revision. Intentional TDD red for CmsAiBriefContractTest before contract exists.
Typed Brief contract implemented after intentional missing-class red. Running 4 question tests plus existing generator tests; normalize server-selected types/options and validate answers against cached questions.
Intentional TDD red: CmsAiImageClientTest before image client exists. Current official Images API docs opened: generate and edit, confirmed JSON reference images and GPT Image 2/2.5 model size/quality contracts.
Image client implemented after missing-class red. Running client plus Brief/generator suite. Real image requests enforce existing Brief targets and managed-media ingest; no arbitrary remote downloads.
Workflow verification: new isolated SQLite HTTP tests cover typed Brief -> rejected invalid answers -> revised Brief -> canonical candidate, changed/wrong-user token. Running after implementing route composition. Browser still pending.
Workflow test unexpected failure 1: Laravel HTTP fake callbacks append rather than replace, so first fixture kept returning questions after answer. Fixture now explicitly resolves questions when answer history is present. Production answer path had returned 200. Re-run with changed fixture only.
Browser measured sticky recap CTA clipped at 674px; corrected side positioning for short heights. Restored heading weights after detecting Tailwind preflight removed browser-default bold. Generator now carries server-owned store facts into content generation as well as planning; requires all planned sections on initial generation.
Real model returned questions first and sections=[]; root cause contract assumed nonempty plan before clarification. New intentional red test for preserving provisional outline while questions are unresolved. Distinct runtime issue count 1; no blind rerun.
Additional image workflow verification: isolated SQLite+fake public disk; verify managed media and same request UUID returns same asset with one provider call. Frontend question wizard 4 types and retained answers passed; configured React act test environment to remove setup warnings.
Real answer revision failed invalid section ID (count 1). Added safe ID-only structural diagnostics and short server aliases in planner payload; output aliases map back to canonical IDs before validation. No raw provider bodies/keys logged.
Intentional TDD red: short model aliases in writer must map to exact canonical section/item IDs. This extends the planner alias fix to copy generation.
Implemented pointer-based reorder fallback after native HTML5 drag did not change the row order in Chrome acceptance (one observed failure). Added generation locking/candidate cache so repeat confirmed-generation requests do not duplicate successful provider calls. Preparing broader CMS regression checks.
Broader regression verification: 70 PHP tests /450 assertions passed, 24 frontend tests passed. Real Q&A accepted radio+multiple-select answers and AI revised 7 provisional sections to 5 relevant sections. Real text+one-image generation now in progress in Chrome, not yet saved/published.
Refine/Start visual defect root cause found: initial CSS scoping conversion replaced substring body{ inside .editor-body{, producing .editor-:scope and removing grid display. Corrected only the damaged selectors; this also repairs the Start background editor columns. Runtime issue count 1, awaiting browser parity.

Final verification run: after FAQ-answer protection, generation cache, direction layouts and mobile renderer viewport binding. Native pointer drag passed (first and second sections exchanged), arrow restored order. Mobile Brief root/document measured exactly390px without horizontal overflow. Saved page36 draft survives refresh; managed hero image loaded1536px in actual private preview. Running affected backend/frontend regression and production build.
## CMS brand/surface phase — 2026-10-17
Approved: capability inventory plus Website Theme -> Page -> Section colors/surface. Baseline codex/social-crm-identity f057afa153adee6cf985da5b16a7d0dd1d9581f2; pre-existing dirty work retained. Root cause: AI seeds all surfaces Light; shared runtime has no global theme palette; page style is an applied snapshot. Implement explicit page colorSource=theme/custom, backward-compatible missing=custom; runtime-only theme palette, no published-document mutation; supported surfaces only, no new Secondary surface. New AI draft follows theme; existing pages opt in. Use existing typography/media/repeatable contracts for inventory. No publish/sync. Initial targeted tests intentionally red before implementation; elapsed/token unavailable.

BR checks:2 palette service tests passed,51 affected PHP checks passed,9 palette frontend checks passed. Tooling path error (one root) corrected explicit cwd; source editing default Windows encoding error corrected utf8. Typecheck identified duplicate hook import from interrupted edit (count1), removed. Existing SectionInspector unrelated type errors remain; no global typecheck success. First build passed; pending final UI browser/save checks.

Broader mounted Inspector audit:390 tests passed,2 failed:missing editorial-journal-rows starter and Photo Marquee repeatable editor. Each issue observed once; affected selector/entry logic not changed by this phase. Recorded as capability gaps, not claimed passing. Browser found duplicate Page color source block from replacing both open details tags (count1); removed second block before final build.

Typography regression: Surface final spread overwrites explicit Page/Section typography color. Intentional initial TDD red themePalette explicit typography tests before resolver correction. Desired precedence Section typography > Page typography > Surface defaults; existing authored customColors stay preserved/restorable. No publish/sync.
## Reliability and exit phase — 2026-10-17
User approved previous priority order and explicitly identified missing abandon action. Scope: explicit keep/close vs discard workspace (never delete saved CMS page/assets); close remaining capability gates; pre-apply quality checks; durable user-owned workspace recovery; inspect retry/idempotency. Extend existing five screens, canonical CMS persistence and existing issue ledger. No publication/sync. During diagnosis Photo Marquee renders image-only controls in shared image-target editor, outside per-item row: prior generic test demanded a text input in that row and is a false capability-gap diagnosis. Update that assertion to edit actual shared image URL, retain add/reorder/delete proof. Editorial journal rows truly missing starter registration. Initial reds for exit dialog and quality helpers declared before implementation. Time/token unavailable.

Recovery HTTP test caught nested validation stripping unlisted workspace UI fields (unexpected count1). Preserve bounded input state after validating envelope/canonical working document; rerun changed controller. This is UI working state, not a publish command. Journal Rows picker now registered and all383 mounted Inspector tests pass; Photo Marquee test edits actual shared image URL. Earlier tooling cwd mismatch occurred again; all file writes now use root cwd, testing isolated separate calls.

Added user-owned DB workspace with optimistic version, soft discard,7-day recovery window and per-operation durable result replay. Network/reload recovery reads the previous task instead of issuing new provider request. Tracked brief/answer/generate/refine/image before provider call. Recovered Brief requires existing token confirmation; expired tokens still require refreshed Brief. Actual token counts shown only if provider response supplies usage; otherwise unavailable, no fabricated price.

Real Chrome recovery caught object/list token loss: PHP associative state JSON roundtrip turned empty color objects into arrays, causing17 canonical type errors on next save (one distinct failure). Corrected storing raw JSON token state, codec restoration on read, raw task-response persistence/replay. Added empty-object roundtrip regression. Actual tested source was homepage32 selected by moved directory order; no CMS mutations, only disposable workspace. Future selectors will scope by exact page card rather than nth index.

Further diagnosis separated null loss from empty-object loss: Laravel ConvertEmptyStringsToNull also corrupted associative request state. Raw JSON-token state persistence fixes both; added empty-string regression. Discarded only our pre-fix source32 QA workspace; reopened Start, new checkpoint kept successfully; another tab recovered latest requirement after GET completed. No CMS page edited. Previous immediate read briefly showed local snapshot while server recovery loading; controls remain disabled until loaded.

Added apply-time server destination review (active product/category/collection, published CMS page, actual GET route; no outbound URL requests). Review mismatch returns visible warnings and requires acknowledgement; admin/API destinations blocked. Isolated fixture initially used Product model defaults against minimal table (one fixture failure), corrected fixture insert through DB. New check makes zero provider calls.

Final390px check found exit-dialog590px wide (one CSS failure): custom max-width replaced shared100% bound and implicit grid track min-width expanded. Fixed explicit minmax(0,1fr) scrim track and width590/max100%. Rebuild then measured viewport/dialog geometry again. Server real Check live destinations button passed without Apply or provider request. Final scope59 PHP tests/326 assertions,394 earlier frontend coverage,11 final focused; no global tsc success.

Final pre-sync audit: real text provider usage was logged but discarded from CMS result; expose request-local numeric usage in tracked response and durable replay, preserving JSON object tokens. Brief now lists per-section existing/catalog/AI media and CTA destination. Run focused recovery regression + frontend build after these changes; elapsed/token unavailable.

P1 expansion: add only existing media_gallery with real source image URLs, three approved layouts; registry now gates seeded newComposition too. New batch-image regression passed. Validate all22 layouts across8 composition types through PageDocumentContract before release.

Layout sweep first run: fixture /images/real.png violates managed URL contract (new fixture issue count1); changed fixture to /storage/cms/media. Inventory count assertion still expected7 after explicit expansion8 (one stale assertion); updated expectation8. No application validator weakened. Run same scope after these concrete fixture changes. Frontend396 passed including383 mounted controls.

Fixture URL issue cumulative2: managed policy requires UUIDv4 filenames, not arbitrary real.png. Inspected MediaUrlPolicy regex; corrected to two valid UUIDv4 fixture paths. Run focused sweep only before broader suite.

Managed fixture root resolved; sweep reached gallery and found one distinct registry typo photo-triptych (not allowed), correct canonical editorial-triptych from contract enum. Registry mismatch count1.

Final sweep61/363 passed; new gallery preset typo resolved. Added generation-time local and server blocked-link preflight. Chrome restored candidate and verified Brief media/action details. Final release rebuild and scoped source test next; no paid request during this audit.

Isolated release first test invocation hit shared Composer junction classmap loading primary Tests namespace (distinct harness issue1, not app failure). Added disposable test bootstrap mapping release App/Tests namespaces and classmap plus APP_BASE_PATH, explicit scope files; no .env copied and no shared autoload mutated.

Explicit-scope harness file path correction: PageDraftApiTest lives tests/Feature/Admin (distinct file lookup issue1), fixed path before invoking tests.

File lookup issue cumulative2: Python path serialization uses Windows separators; previous slash replacement did not match. Replaced actual backslash path; no test executed on failed lookup.

Isolated release harness now loads release source correctly; no .env means missing APP_KEY (environment blocker1). Set ephemeral random test-only encryption key in disposable bootstrap, never production credential; SQLite memory checks remain enforced. Rerun after explicit environment correction.

Isolated release gate passed61 tests363 assertions using release App/Tests class mappings and ephemeral test key; release Vite build passes. Sync now proceeds under conditional authorization; unrelated inventory/Fashion edits excluded.
