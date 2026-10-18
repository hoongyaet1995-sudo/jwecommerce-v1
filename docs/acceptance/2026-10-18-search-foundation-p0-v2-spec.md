# Search foundation P0 v2 — automatic-first functional specification

> Legacy-data overwrite policy and catalog sample placement are superseded by [v3 catalog addendum](2026-10-18-search-foundation-p0-v3-catalog.md): current project legacy SEO may be regenerated with preserved before/after audit. Future human overrides remain protected.

2026-10-18. DESIGN / PROTOTYPE ONLY. Baseline848d89e1471aca59ce6d3a5a4e0939a82e3329d0.

## Authority and purpose

User explicitly extends P0 to include AI optimization. The ordinary customer maintains business content, not SEO fields. System automation is always available; LLM improvement is optional background assistance. Manual editing belongs in Advanced settings. One platform contract serves every industry theme.

This v2 supersedes manual-first UI ownership and the AI exclusion in [v1](2026-10-18-search-foundation-p0-design.md). Retain v1 technical rules for published-content rendering, safe metadata, same-origin canonical/redirects, sitemap eligibility/invalidation, private data, truthful publication and source-faithful layout. No production code is integrated or deployed in this design phase.

Prototype: /page-template-sample/search-foundation.html?screen=site|page|catalog|review. The HTML loads search-foundation-v2.js and supplemental v2 CSS. Original v1 JS remains an archived unused local sample, not a second active contract. All generation, model availability, jobs and checks are visibly simulated. Reload resets sample state. No LLM/API/network generation requests, real saves or publication.

## Core product rules

1. Base metadata is deterministic and generated locally from trusted record data; LLM failure cannot prevent CRUD or normal publication. No scraping our own page or reading private account/chat/order context.
2. Website identity/domain read trusted shop/deployment configuration. Domain is read-only in the normal interface. Never derive the canonical origin from a browser localhost URL, an untrusted Host, or AI. Origin changes belong to separately reviewed deployment/domain migration.
3. New/meaningfully edited records get automatic title/description. Active/public records update canonical and sitemap independent of LLM approval. Draft/inactive records do not enter public discovery. A schema contract existing in DB is not proof of public output.
4. AI optimizes only title, description and internal suggested content topics, based on confirmed public business/content facts. No meta-keywords output, new services/locations/prices/warranty/stock guarantees, schema Offers, visible body edits, slug edits or automatic publication by LLM.
5. Title suffix is system-managed, added once; real entity name and authored plain-text description are fallback inputs. Manual per-field overrides have highest priority, then valid same-revision accepted AI result, then deterministic base. Empty manual override is rejected or explicitly cleared; it is not a broken blank public title.
6. Editing relevant source invalidates prior AI output for that source revision and restores a factual current fallback. Manual overrides remain protected. Old AI text is never silently kept as current after business facts change.
7. CMS AI results are draft-only and take effect with normal CMS publication. A later finishing AI task cannot silently publish after the user has published an earlier draft. Catalog follows existing immediate-save semantics for active records. Do not invent catalog version/publish functionality in this phase.

## Modes / decision on new product and category

| Mode | Base metadata after save | AI completion | Human action |
|---|---|---|---|
| System only | Automatic | No LLM call | None for SEO |
| Auto optimize (recommended after enabling AI feature) | Automatic immediately | Schema/fact/permission/revision validated result replaces only unlocked fields | No routine SEO approval; exceptions/factual questions only |
| Review first | Automatic immediately | Save candidate, show before/after | Accept optional AI changes; otherwise keep base output |

System generation is on by default. AI auto is the recommended mode only after the merchant explicitly enables Search optimization under Integration → AI Connect → Use AI For, with valid shared text model and task allowance. Existing sites must not start paid bulk generation on migration. AI feature off/disconnected/quota exhausted falls back to system-only output. Switching modes changes future job behavior, does not bulk rewrite existing pages, unlock overrides or retroactively approve pending candidates.

No separate approval queue for every product just to make it discoverable. Review-first is available for customers with stricter editorial processes. Product information accuracy remains a business-content responsibility; SEO automation cannot confirm actual service coverage. Future AEO/GEO visibility is not an immediately updated external database.

## Lifecycle contract

| Event | System action | AI action | Public behavior |
|---|---|---|---|
| New draft CMS/product/category/collection save | Store source + base metadata | Queue if enabled and eligible content sufficient | Draft/inactive stays excluded |
| Active product/category/collection save | Commit base output + eligibility changes; invalidate shared discovery caches | Queue changed relevant source after commit | Base output available immediately; valid auto result can update metadata later |
| CMS draft edit | Save canonical draft/version, invalidate draft review | Queue draft revision | Published document/head/URLs stay unchanged |
| CMS publish | Validate current revision, capture public metadata + content, update canonical/sitemap | No waiting on optional AI | Use current base/accepted metadata; late job stays in draft |
| AI finishes | Check feature, model result, source hash/version, eligibility, override locks | Apply or store candidate according to current policy | Atomic update only within the correct lifecycle |
| Source changes during job | Reject old result; coalesce latest work | Requeue latest meaningful saved input within limits | Never apply old facts |
| Inventory/price-only tick, reorder, page view | Existing commerce runtime updates as usual | No metadata LLM request for irrelevant changes | No generated stock/price claims become stale |
| Category membership change | Runtime listing/sitemap eligibility recompute as needed | Only if actual relevant semantic source hash changed | Not one LLM call for every stock/member update |
| Slug/status/publication/deletion | Re-evaluate public access, canonical, redirect, sitemap | Cancel/discard inappropriate work | No ghost URLs or private/draft output |
| Manual override toggle | Lock/unlock each metadata field explicitly | Skip locked fields; skip entire job if both title/description locked and no requested topic work | Preserve operator intent |

Relevant fingerprint includes entity type/ID, source revision, name, authored description/visible public content, page language, allowed business facts, prompt/schema version, and brand context. It excludes random updated_at ticks, analytics, secrets and arbitrary admin UI text. Stable job key prevents duplicate requests. Global brand changes deterministically refresh suffix; no surprise whole-site AI batch. Revert uses metadata audit/history where available, never reverses business facts or auto-publishes CMS.

## AI boundary / reliability / budget

- Existing AI Connect provider and selected text model; new use-for flag Search optimization. Do not require image model or introduce a second key store. UI shows provider unavailable/pending/review-ready/applied/stale/retry/base-fallback states, not a fictional SEO score.
- Permission checked server-side for trigger/review/apply; current entity readable and allowed facts resolved server-side. Model sees business content as data, not instruction. No browsing tools, self-fetches, private data or arbitrary URL execution.
- Strict structured response fields: title, description, suggestedTopics; enforce allowed keys/lengths/plain text and content constraints. Uncertain or unsupported claims reject result or request fact review; source-bound prompting alone is not proof of factual accuracy. On rejection keep safe fallback. Never assert automatic factual checking is infallible.
- Durable jobs after commit; debounce/coalesce rapid edits, per-entity dedupe, bounded attempts/backoff, task quota and concurrency. Request timeout/provider error → visible failure + base output, no endless retry loop. UI Retry reuses latest valid source; stale/cancelled jobs do not consume repeated automatic retries.
- Once source and connection/settings are unchanged, do not repeatedly call failed provider on every save or page view. Retry requires bounded scheduled attempt, explicit action, or a relevant source/configuration change.
- Record task ID/entity/source hash/model/status/timestamps/error class, returned usage where available, and before/after metadata. Do not log credentials or unnecessary full private payload. Cost only if actually calculable; otherwise show usage/unavailable, not fabricated estimates.
- Provider/model changes do not mass regenerate. Optional bulk regeneration is outside P0; later explicit scoped action with workload confirmation. Existing live content untouched during feature migration.

## Screen responsibilities / functionality specs

### 01 Website automation — Settings → Search visibility

Primary: read-only connected brand/domain, automatic baseline explanation; three modes Automatic optimize / Review first / System only. Brief lifecycle and effective protection. Advanced: existing website live/preview policy; saved AI connection link and task allowance/status in integration. No editable domain in routine SEO form. Save only website preferences; confirm sitewide noindex change, validate settings, handle conflict/loading/errors. AI feature permission/readiness is real backend-derived state, not an enabled-looking dummy.

Prototype exposes provider availability as a labelled sample scenario and already-enabled auto mode to show the intended connected experience. It does not represent actual stored consent, model selection or quota.

### 02 CMS Page — Page settings → Search

Primary: effective search preview, source badge (System / AI / Manual), freshness/source revision, Optimize/Retry, compare/apply when review mode, draft/public distinction. Advanced collapsed by default: individual manual-title/manual-description locks and inputs, inherit/exclude. Source content is edited in existing Content controls; a sample-only collapsed content-change scenario demonstrates invalidation. Saving metadata or accepting AI only saves draft, preserves current publication. Topics are internal AI guidance, not keywords tags and not proof of search volume.

### 03 Catalog lifecycle — existing Product/Category/Collection editors

Primary: generated output and AI state, not mandatory SEO forms. Keep existing catalog content controls as truth. Advanced: same per-field overrides, no duplicate catalog CRUD. Sample has Product/Category/Collection switches, Create sample record, existing-content fields and Active/Draft to demonstrate the lifecycle; these controls do not become a new catalog management page in integration. Same shared renderer/head metadata contract across themes.

### 04 Publish review — current CMS Publish action

Primary: automatic blockers/suggestions, current metadata source, business-fact confirmation. AI pending/off/error/review candidate is non-blocking if valid base exists. Readiness/confirmation binds exact current content revision; server recheck and409 protect concurrent writes. Business acknowledgement is not SEO expertise. Sample checks are explicitly simulated; real results use service checks. A failed check does not change public output. Catalog does not acquire this CMS publication screen.

### AI comparison dialog — shared within screens02/03

Show effective before vs proposed after, source revision, field locks and scope. Review mode Apply affects unlocked metadata only; auto mode View result is read-only. Close/Escape/focus restoration, stale result rejection, request pending disabled action, error fallback. No Apply button for already applied or invalid/stale result. In actual implementation, preview locked fields as unchanged so comparison does not imply they will be overwritten.

## Acceptance additions (all pending application implementation)

Retain SF-P0-01..08 in v1. Add:

| ID | Verification |
|---|---|
| SF-P0-AI01 | New product AND category AND collection: save draft vs active; base title/description and correct noindex/canonical/sitemap without AI/provider; no mandatory SEO input |
| SF-P0-AI02 | Connected auto mode: after-commit job; real selected-model response; strict validation; per-field unlocked application; refresh and second session reflect persisted output; no visible body/URL changes |
| SF-P0-AI03 | Review mode: base stays public; candidate before/after; reject/ignore leaves base; authorised apply correct revision; no indexing dependency on approval |
| SF-P0-AI04 | CMS generation/apply → draft only; publish old revision with pending job; late job never modifies published snapshot; history preserves approved SEO fields and current global policy |
| SF-P0-AI05 | Job stale/deleted/inactive/source changed/manual lock toggled mid-flight/mode off during job: safe discard or current-policy handling; no overwrite of operator data or draft leak |
| SF-P0-AI06 | Timeout/auth/quota/invalid JSON/unsupported facts: base output intact, bounded attempts, honest status; retry latest source; connection recovery without blind loops |
| SF-P0-AI07 | Rapid edits/double save/reload/status-only changes/stock ticks/page views do not produce duplicate or irrelevant calls; actual usage logged, no keys in payloads/logs |
| SF-P0-AI08 | Advanced field locks survive save/refresh/relogin; unlock restores latest current-source output; both locked avoids unnecessary generation |
| SF-P0-AI09 | Unauthorised request denied; cross-entity task IDs protected; prompt injection in product text cannot alter scope/tools or expose secrets; new feature migration causes no billable bulk run |
| SF-P0-AI10 | All four screens/dialog in actual Admin: keyboard/focus/390mobile/1536desktop, source badges, empty/error/stale states and Builder/public parity. No generic mock pass presented as real check |

Acceptance remains ability to publish accurate readable public content and automatic metadata/discovery, not external indexing/ranking/AI citation or acquisition. Structured data, social cards, Search Console/lead attribution, public multilingual URLs/hreflang and automatic FAQ-knowledge import remain later scope. New product facts do not instantly retrain external answer engines or update the support knowledge pipeline unless its existing explicit eligibility permits it.

## Verification ledger

2026-10-18: browser inventory again reports disabled by Work settings. No browser automation substitute attempted. Visual/mobile/interaction QA pending; user can open localhost sample. CSS attempted patch failed once because it targeted an incorrect full minified line; file unchanged. Resolved by a separate supplemental v2 stylesheet, no blind repeated patch. Original issue not a functional application failure. Syntax/HTTP smoke checks to be run after sample changes. Elapsed/token unavailable. No application code/DB/provider/production writes, commit, push or release in this design phase.

Smoke result: node syntax passed; pure-function checks rendered4templates and verified auto apply, manual-title protection, review candidate, stale rejection and AI-off base path. HTML/baseCSS/v2CSS/v2JS all localhostHTTP200. This is not browser/DOM/visual acceptance. Added completion-time provider/off guard and empty-manual-override validation after reviewing edge states; final syntax check passed. No LLM used.
