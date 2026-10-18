# JTG reusable search pilot — local only

## Accepted closeout boundary — 2026-10-18 Asia/Kuala_Lumpur

User confirmed this phase closes on JW Ecom's technical discoverability/readability foundation, not on how quickly AI providers refresh a two-day-old pilot. AI mentions/recommendations and external index status are ongoing observations, not blocking acceptance of the implemented local foundation. No guarantee or fixed provider update interval is implied.

Scope wording remains precise: active/published page eligibility, canonical/sitemap/robots, public HTML including authored FAQ content, shared business identity and the implemented JSON-LD types. There is no dedicated FAQPage JSON-LD emitter in this release; FAQ prose readability must not be mislabeled as that feature. No public AI action/plugin endpoint or direct platform submission integration was implemented or required for this phase.

Local code/test/browser and evidence closeout accepted per the existing records below. New structured-data changes remain local, with no commit/push/deploy permission. Keep the one manual Refresh SEO policy, no nightly/per-save AI and no unrelated worktree changes. Next phase selection is being clarified before starting a different implementation scope.

User scope: unified business identity, structured data, indexability checks and AI exposure baseline. Preserve UI and one Refresh SEO. No commit, push, deployment, nightly schedule or per-save AI.

Design: reuse Shop Profile as business facts; legacy shop_name fallback. Shared Laravel identity consumed by SEO metadata and JSON-LD. Public graph includes only identity already represented by storefront name/logo, never registration/tax/private contacts. Product facts use actual public model; omit offers/ratings rather than infer purchase eligibility or fabricate stock. No new UI or dependencies.

Mapping: existing Shop settings -> MySQL -> shared identity -> public head; active/published entities -> canonical metadata -> graph; existing Refresh SEO -> internal audit snapshot. All stores use the same services, with no JTG branch in application code.

External boundaries: localhost cannot be indexed by public engines. Crawl readiness is not index confirmation. Actual indexing requires verified Search Console/Bing evidence for the customer domain. AI baseline must retain platform, query, search mode, timestamp, actual answer and citation URLs; unavailable/access-blocked platforms are not zero visibility.

Test ledger:
- Initial intentional TDD red: SearchPilotTest before services/head output exist; expected missing class/graph. Unexpected failure count 0. Next implement shared services.
- Red result: missing identity class confirmed. Fixture used unsupported price_display=hidden (unexpected fixture issue 1); corrected to hide and explicitly enabled enquiry feature for the test. No application validation weakened.
- Green run planned: SearchPilotTest and existing SearchFoundation/Producer tests after shared identity and head integration. Purpose: public safety and metadata compatibility.

Current closure: local implementation accepted; 32 tests / 298 assertions pass, 44/44 eligible public URLs verified. Four consumer platforms each have two real independent web-search samples saved in MySQL (8 total). Actual customer-domain indexing remains unavailable because JTG has no official domain. Time/token usage unavailable.

User confirmed JTG has no official domain; ecom.jwaistudio.online is a JW Ecom demo. Never attribute demo indexation/citations to a JTG customer domain. Existing Shop Profile has name/logo but missing tagline/email/phone/address/city/state/country; do not fabricate or silently publish unrelated contact records.

23 tests / 258 assertions passed after initial identity/graph implementation. Next targeted run adds audit persistence on existing Refresh SEO and regression coverage for shared branding. Intentional initial red observed; fixture correction resolved; no unresolved unexpected application failures.

- Follow-up regression passed 27 tests / 281 assertions including Refresh audit capture and ShopBrandingTest. No provider calls added.
- Intentional TDD red for SearchExposureBaselineTest: missing evidence-ledger class (2 expected failures). Implement append-only validated internal evidence, no new Admin UI. Records are never overwritten by Refresh SEO. Aggregate exposure rates stay null because mixed domain/surface/prompt samples are not comparable.
- Baseline ledger + pilot tests passed 7 tests / 31 assertions. Actual MySQL capture saved 44 records without AI calls. HTTP sweep passed 44/44 (17 product,8 category,6 collection,13 published pages); all eligible URLs present in sitemap.
- Evidence helper issue 1: Windows default cp1252 could not decode Chinese raw answer; changed explicit UTF-8 read. No application behavior affected. Follow-up evidence generation is justified by this encoding correction.
- Maintainability review: evidence observations moved to a dedicated indexed, append-only MySQL table with unique import fingerprint. No daily/request/storage-count quota. Reports return latest 1000 observations with explicit returned/total counts; historical rows remain stored. Initial local 2-row StoreSetting prototype remains as a retained snapshot, no longer read by runtime; exported files replayed into final table. Local named migration only; production unchanged.
- Next verification: focused full Search/branding regression after ledger storage change, observation idempotency, draft isolation and controller branding fallback. No unrelated tests/builds needed because no React/CSS changes.
- Storage change verification passed 29 tests / 289 assertions. Final review found audit should refresh after the last task, not retain pre-refresh facts; added final-task capture and regression verifying an initially missing description becomes ready in the final persisted audit. Homepage avoids duplicate self breadcrumb. Next run validates these final logic changes.
- Final completion regression passed 30 tests / 293 assertions. Independent review identified fingerprint canonicalization and avoidable loading of full answer payloads during audit. Reproduce fingerprint using equivalent omitted/explicit scope defaults plus reversed input order (intentional red); then use ordered identity fields and counts-only audit query. This is distinct from the resolved fixture/evidence encoding issues.
- Both review fixes verified independently; final suite passed 32 tests / 298 assertions. No important review findings remain.
- Consumer baseline: all four platforms now actually accessed through normal browser sessions. Two independent questions each (non-brand suppliers, explicit brand/official-site check); no JTG mention in the four discovery samples and no confirmed JTG official website in the four brand checks. Not a 24x4 complete study.
- Evidence import issue 1: DeepSeek rendered multi-# URLs rejected by Laravel URL validation; retain every raw link in evidence, exclude malformed links from validated citation_urls rather than changing validation or inventing corrected source URLs. Claude brand response malformed autolinks likewise retained only in raw evidence. Retry imports only not-yet-recorded samples after this data correction.

## Final mapping and evidence

| Capability | Status | Producer -> consumer evidence |
|---|---|---|
| Shared business name/logo ownership | CLOSED | Existing Shop Profile MySQL -> BusinessIdentity/ShopBrand -> SEO facts and actual public head; conflicting legacy-name and shared-brand tests |
| Public structured graph | SYSTEM_MANAGED | Published/active MySQL content -> SearchHead -> safe JSON-LD; actual HTTP44 and browser homepage/PDP |
| Technical audit | INTERNAL_ONLY | Existing Refresh SEO start/final task -> SearchPilotAudit -> MySQL snapshot; actual local capture and protected API/service tests; no new UI |
| Exposure evidence | INTERNAL_ONLY | Real consumer UI answers -> validated CLI -> indexed MySQL observation -> report; 8 persisted rows, 2/platform |
| Official customer-domain indexing | DORMANT | No official JTG domain / verified Search Console or Bing property yet; not claimed accepted |
| Unconfirmed business contacts/address/legal identity | DORMANT | Existing Shop fields remain available; missing facts reported, no fabricated values or third-party identity imported |

Real MySQL repeated import of the final Gemini sample returned the same UUID d8f866d2-3c4f-4635-8e2d-76815ae36eab; total stayed8. Discovery brand_mentioned=false for all4 and target_cited=false for all8. Brand checks contain negative mentions, not recommendations; mention_rate remains null.

Actual core-data ordered-row hashes stayed identical across products46/categories18/collections11/page_documents31/search_entries93/search_tasks187/search_refreshes1. No actual paid all-site Refresh run was added in this phase; UI retains the prior 92-record result. Existing unrelated source work preserved by scoped edits only; index empty, HEAD still ab2e66a92d96aea4d3f36b7427dae3b7700ed26d. No commit/push/deploy or scheduled task created.

User-facing evidence: C:/Users/User/Documents/Codex/2026-10-18/cua-repl-chrome-hoongyaet1995-gmail-com/outputs/jtg-search-pilot/acceptance-report.md and adjacent JSON/screenshot files. 24-query tri-language question pack prepared; current initial baseline is8, not24x4. Formal rollout first needs confirmed customer facts/domain and separate publication/deployment authorization.
