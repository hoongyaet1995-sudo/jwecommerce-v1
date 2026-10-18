# P0 v3 — existing catalog SEO controls and authorised legacy regeneration

Date2026-10-18. DESIGN SAMPLE ONLY; no application integration/provider run/release. Extends [v2 functional spec](2026-10-18-search-foundation-p0-v2-spec.md). Overrides only the legacy migration default and sample catalog placement. Other v2 lifecycle/private-data/automatic-mode boundaries remain.

## Explicit user decision

Existing SEO values in this development project may be overwritten by AI and re-created to evaluate AI SEO accuracy. Do not require unlocking each old field. auroracommerce.io is old dummy data, not evidence of the real domain or an auth/configuration failure. User authorises future actual legacy rewrite as part of this phase; this turn prepares the revised requested sample. Do not repeatedly request permission for each legacy value when executing the approved phase. Paid workload must be concrete, measured and bounded before an actual bulk run; prototype makes no paid requests.

This project-specific permission does not become an automatic overwrite rule for all future merchant migrations. New human overrides after migration remain per-field protected. Never treat old SEO as instruction or as trustworthy business facts.

## Placement and scale

Product: retain existing Product editor, tab5 SEO & Social Meta; upgrade preview + source/freshness/AI status, manual fields in collapsed Advanced. Other tabs/layout/footer unchanged at integration.

Category: retain existing Category edit modal, upgrade existing Search Engine Listing Preview in place. Preserve image, parent, status, description and save action. Collection: add same shared preview/AI/Advanced block inside existing Collection editor without replacing association controls, product picker or original save lifecycle.

There is one metadata contract/store per entity, not new SEO copies. Shared preview reads the trusted configured origin instead of Aurora dummy. Product SEO title must reach actual storefront output, not remain a preview-only field.

No1,000-form screen. Use existing server-paginated/searchable catalog lists. This prototype demonstrates10rows/page across1,000synthetic records (940products/40categories/20collections), search-by-name/ID, state filter, explicit row selection and single-item editor. It is not the real data count. Real integration uses Laravel/MySQL bounded pagination and aggregated counts, not loading all records client-side. Existing catalog list gets bounded status cues, not a separate duplicate list; prototype top navigation is a review aid.

Sample Product dialog mirrors its known current tab/preview/footer composition; non-SEO tabs shown only as context, not claimed interactive. Category/Collection dialog shows a compact existing-description context above new search block; not a full source-faithful replacement for their entire forms. Existing non-SEO inputs/associations will remain intact during integration. Dialog state is sample only.

## Authorised batch and failure behavior

- Scope by entity type and selected or matching records, all pages included only when clearly stated. Sample prefers selected matching records; otherwise all matching legacy records. Never assume visible page means the entirecatalog and never silently change scope when filters change.
- Before actual run show exact eligible count, mode, saved real model, work bound/allowance and whether existing overrides are included under this project's approval. No guessed fee; actual provider usage/cost only where available. P0 expands to this bounded legacy-regeneration entry to support requested accuracy audit; do not introduce a general unlimited bulk tool.
- Preserve prior title/description as immutable job audit before generating. Build facts from current name, authored summary/description and allowed business content. Exclude old dummy origins/brand defaults from model facts. Automatic apply only after validation/current revision check; review mode stores candidate.
- Failed/rejected/pending jobs retain prior valid SEO, not empty fields or an unreviewed partial result. New records without prior SEO use deterministic base. Stale source jobs cannot apply; refresh/requeue latest according to limits. Failed-only retry; no whole-batch recharging.
- Completion keeps before/after, facts/source revision, model/job/status, actual returned usage. Manual additions after job launch stay protected. Atomic replacement of permitted fields only, no changes to name/description/price/stock/slug/media/associations/body/private data.
- CMS results remain draft until publish; active catalog allowed auto apply as approved. No AI task publishes CMS in background.

## Accuracy acceptance

Use real selected provider only during application acceptance, not fake generated text labelled realAI. Select varied existing Product/Category/Collection items, including sparse content, duplicate-like product names, Chinese/English, quote/display/direct modes, legacy dummy brand/origin and uncertain service facts. Representative records must cover every affected type before authorising the concrete bulk execution step already in scope; no per-item approval burden.

Review: correct record/size/type; no invented facts/locations/prices/delivery/warranty; no accidental Aurora branding; title and snippet appropriate and distinct; meaning preserved; correct language; manual override behavior; missing facts yield conservative output or review-needed state. Token/model evidence recorded. Rejected output is not counted as successful application. Read actual HTML title/description after save, not just green task status. Automated validation cannot prove all factual precision; report exact assessed cases/failures, never promise100%accuracy.

Acceptance extensions: SF-P0-AI11 legacy approved values replaced without per-fieldunlock, prior snapshot recoverable; SF-P0-AI12 failed batchitem keeps prior metadata and retry-only-failed; SF-P0-AI13 server pagination and stable selected/matching scope; SF-P0-AI14 current Product/Category controls retained, Collection added, no duplicated CRUD or metadata; SF-P0-AI15 actual LLM audit + actual public HTML consumption with measured evidence.

## Sample interactions and verification ledger

HTML now loads v2 foundations + v3 catalog overrides. Synthetic list/search/filter/pagination; edit one item; Product/Category/Collection preview; legacy optimization; simulate completion/failure; batch selected/matching confirmation; before/after compare; future manual locks. All values/jobs are simulated and reload resets state. No real API/DB/LLM calls. Existing v2 remains a sample dependency, not production implementation.

Browser automation was disabled in previous inventory; no screenshot/visual acceptance is asserted. Static syntax, template logic and HTTP checks follow. Keep pending real desktop/mobile/dialog/keyboard and source-fidelity acceptance visible. No commit/push/deploy in design stage. Elapsed/token unavailable.

Static checks passed: v3 node syntax; pure-function smoke verifies940products/94pages, filtered search,40categories/20collections, failed replacement retains previous output, successful replacement removes dummybrand while preserving before snapshot. HTML/v3JS/v3CSS eachlocalhostHTTP200. These do not prove realLLM accuracy, DOM interactions or visual fidelity. Browser acceptance remains pending.

## 2026-10-18 explicit user supersession
The user replaced catalog Regenerate legacy SEO dialogs and manual per-entity optimization with one Settings/Search Refresh SEO action covering Product, Page, Collection and Category. Recent optimization tasks are removed from operator UI; durable internal audit stays. Existing preview/manual locks remain. Global refresh preserves manual overrides, applies validated results, writes CMS drafts only, and respects daily allowance. See implementation ledger and appended browser acceptance; earlier v3 catalog batch UI requirements are historical, not the current operator workflow. No release approved.

2026-10-18 accepted policy supersession: no daily task cap; no AI task admission from catalog create/update, PDP content save, CMS draft save/restore. Preserve deterministic metadata, redirects/cache, manual policies and draft/public contracts. Full snapshot refresh is triggered manually or nightly02:00 Asia/Kuala_Lumpur. Shared service deduplicates active runs; system actor null. Settings API strips legacy daily_limit; UI reuses existing design and removes limit/reviewchoice, retains provider/system choice. Existing run1 resumes retired daily deferrals with no second full regeneration. Local Windows task JW Ecommerce - Nightly SEO Refresh executes only search:refresh, next2026-10-19 02:00+08; actual task launch returned0 and reusedrun1. Local Search-only recurring worker to be registered after live acceptance; production scheduler registration remains deployment responsibility (no release authorized).

Verification:92affectedtests/753assertions passed21.61s; extra worker legacy-deferral/preferences cleanup regression1/16 passed4.28s (overlap, not additive). Frontend3 passed2.19s. AdminSSR buildexit0/Vite14.45s, existing missingasset/chunkwarnings. Browserlight/mobile390 scrollWidth390, noquotainput/tasklist, Savepreferences succeeded. Live runprogress63/92 includinglockedskip1, product46applied/category16applied, nofailures; finalfourkindchecks pending. Original stop history retained; resumed code/tests have no unexpected failure, two discoverypath mistakes documented. Measured modelusage finalpending; totalengineeringelapsed/tokensunavailable.
