# Search foundation P0 — design and acceptance contract

> Superseded for AI scope and automatic-first UI by [P0 v2 specification](2026-10-18-search-foundation-p0-v2-spec.md). Retain the technical foundation acceptance rules below; v2 explicitly includes AI optimization and places manual editing in Advanced settings.

Date: 2026-10-18. Baseline: 848d89e1471aca59ce6d3a5a4e0939a82e3329d0.
Status: DESIGN PROPOSAL / UI SAMPLE ONLY. Not application integration, publication or release approval.

## Purpose and authority

User requests a reusable P0 search foundation across every industry theme, with rules/boundaries/acceptance agreed and new controls previewed before integration. Preserve all accepted storefront and Builder appearance. Existing dirty inventory, Fashion and other files are out of scope. This document is an implementation handoff proposal, not a CLOSED capability claim.

Recommendation: shared website defaults + entity overrides + server-owned eligibility/output checks. Reject two alternatives: a central form duplicating all entity editors (drift and unclear ownership); theme-specific SEO implementations (inconsistent output and repeated maintenance). Website Theme owns appearance, not search policies. Page editors own authored overrides; system services own canonical URLs and sitemap eligibility.

Prototype: /page-template-sample/search-foundation.html. Four screens via ?screen=site|page|catalog|review. English UI follows existing JW dark Admin patterns (DM Sans, purple actions, bordered cards, neutral controls). All results/data are explicitly simulated; no API calls, real saves, AI calls or publication. Sample values use example.com, not a claim that this is the configured canonical domain. Navigation/state is ephemeral; reload resets sample fields. Global preview change and publication confirmation use native accessible dialogs. Do not deploy prototype as a production settings surface.

## P0 decisions and boundaries

### P0-01 Configuration ownership

- Protected Settings → Search visibility: website identity/title suffix, preferred HTTPS origin, public-vs-preview indexing policy, read-only system rules.
- Reuse shop identity; default to configured shop name. Search display-name override is explicit and never writes shop legal/billing identity.
- CMS Page settings → Search: search title/description, existing slug, inherit-or-exclude page policy, effective URL/indexing preview.
- Existing Product SEO & Social Meta and Category SEO & Metadata keep their identity; bind output properly. Collection gains the same title/description fields. This phase does not deliver social metadata/image generation despite existing tab name.
- Public shop listing uses configured listing title/description fallback. No additional all-products editor in P0.
- Do not silently rewrite stored metadata or existing live indexing policies on migration. Global defaults must preserve existing behavior. Remove misleading Aurora/warranty/shipping defaults for new/empty fields without overwriting authored values.
- Global settings use protected server persistence and concurrency checks. CMS page metadata/indexing are part of its canonical document, publication snapshot and history. History restores do not restore website-global policy or catalog facts.

### P0-02 Metadata resolution

- Title: non-empty explicit entity search title → actual entity title; optionally append configured website display name once. No duplicate automatic suffix, no permanent hardcoded theme branding. Preserve merchant text; normalise only the system-added suffix. Empty CMS description → authored page description; product → plain-text subtitle/description; category/collection → authored plain-text description. If no factual description exists, omit rather than invent.
- Title/description escaped in server HTML, never raw rich HTML; one title and at most one description. No fixed character threshold treated as a ranking guarantee or hard publish gate. Storage validation retains sensible field limits.
- Snippet preview is illustrative; no assertion Google must display it. Search metadata is independent of visible H1.
- CMS Save draft does not alter public head. Catalog saves keep existing immediate active-record behavior; do not imply catalog drafts/history are introduced.

### P0-03 Public content readability

- Main heading, authored visible paragraphs, FAQ answers and meaningful internal anchors must be available in initial HTML for eligible CMS pages/homepage. Preserve the same document, order, text, classes, responsive structure and motion semantics across Builder/public. Product/catalog pages retain their existing server output.
- Choose SSR/pre-render mechanism during implementation planning. Use canonical published document and authoritative runtime/catalog adapters. Never create manually duplicated SEO text, a crawler-only rendering branch, hidden keyword content or an unrelated simplified page.
- Hydration/interactive takeover must not create duplicate visible sections, duplicate IDs, navigation jumps or duplicate form submissions. Only public published payload is emitted. Failure must not serve an empty app shell as an apparently healthy successful page; preserve last published content and provide an honest failure state/status.
- Normalise heading semantics in shared dialogs where appropriate, preserving appearance and accessible dialog labelling. Multiple H1s are a semantic review, not proof of a ranking penalty or an automatic rejection of all existing pages.

### P0-04 Discovery, canonical and URL lifecycle

- Preferred origin: server-validated HTTPS origin, no credentials/query/path fragment. No client-provided Host-derived canonical. An origin change requires deployment/domain migration review and cannot be silently treated as a routine text edit. UI must show old/new origin and impact before committing such a change; specimen origin field is illustrative, not proof domain ownership is verified.
- Canonical system-generated, same-site, resolves to a valid public destination. Active home uses /. Its equivalent /pages/{slug} should redirect to / while preserving benign tracking parameters; exact handling of former home remains explicitly tested against active-home selection.
- Stable entity slugs; draft CMS slug changes create no public redirect until publication. Publishing a new slug atomically updates canonical and creates previous-live-URL 301; failed publication leaves old live URL intact. Repeated edits point to current destination without loops/chains; reserved paths and collisions rejected. Categories/collections/products follow their existing immediate-save lifecycle.
- Parameters: remove tracking, sort/view and incidental selection params from canonical; valid paginated listing pages retain own page canonical and crawlable navigation. Arbitrary tag/price/search filter combinations excluded from sitemap; page-level noindex policy is intentional. Do not blanket canonicalise every paginated page to page1. Curated categories/collections retain self-canonical. Test the exact actual filter names during implementation.
- Sitemap XML: only published eligible CMS and active reachable catalog pages + /shop; home once; absolute preferred-origin URLs, XML escaping, real public-content lastmod (not draft saves/checkpoint time). No login/private/support attachment/API/preview/sample/draft/filter URLs. Handle scale via bounded queries/chunks and sitemap index if necessary. Invalidation after publication, unpublication, eligible catalog writes and policy/home changes; no database scan per arbitrary bot hit.
- robots.txt includes sitemap reference and deliberately allows resources necessary to render public pages. robots.txt is not access control and is not a reliable removal mechanism. Do not Disallow pages whose noindex must be crawled. No user-editable arbitrary robots text in P0.

### P0-05 Eligibility and confidentiality

- Effective index eligibility = public route AND public/published record AND website permits AND entity not excluded. Exclude can never override private-route protection or website-preview policy. Publishing with deliberate noindex is valid.
- Auth/account/order/cart/checkout/enquiry/quotation/preview/history/admin/API/attachments must have route-appropriate access control and noindex where applicable. Preserve existing token/session/ownership checks; do not alter public commerce or signed attachment semantics. Verify no shared cache leaks private content. Public FAQ API is distinct from private data and is not a substitute for an HTML FAQ destination.
- HTTP status accurate (missing404, unauthorised existingauth flow, relocated301). Draft unpublished content never leaks via metadata, bootstrap, sitemap, SSR cache or history endpoints.
- On new empty sample/demo scaffolds default to review-needed; do not automatically label current live merchant content untrue or switch entire production site to noindex.

### P0-06 Merchant truth and publication check

- Automated checks cannot prove actual services, location, availability, image authenticity or project completion. AI illustrative images are not evidence of real work. Require three content-revision-bound confirmations for reviewed CMS publication: business facts accurate; images/projects labelled honestly; demo promises and private data removed.
- Evidence: actor, reviewed content hash/lock version, timestamp; content edits invalidate previous acknowledgement. No claim this is legal verification. Existing published pages remain live; new checks run for subsequent publishes.
- Technical blockers: unsafe/invalid destination/slug, unsupported/invalid published document, missing expected main content output, known broken required internal destination, private-data eligibility conflict. Check managed internal destinations rather than uncontrolled remote fetching/SSRF-prone link validation.
- Suggestions: description length/specificity, authored alt review, duplicate wording, heading semantics. Do not block on unavailable external validators or subjective SEO score. Missing optional decorative alt is not a blocker.
- Check result is stale if draft changes. Server rechecks revision and permissions during publish; conflict409/reload, never last-write-wins. Failed check/publish does not modify live content. Keep existing publication gates and history checkpoints. Sample ready state is deliberately simulated, never written as an actual pass.

## UI responsibilities / functionality specs

| Screen | Entry / owner | Editable controls | Actions and states | Never responsible for |
|---|---|---|---|---|
| 01 Website defaults | Settings → Search visibility, website settings | Display name, suffix, preferred origin proposal, live/preview | Save, validation, confirm sitewide noindex; current effective rules | Theme colors, page content, arbitrary robots text, ownership claims |
| 02 CMS page settings | Existing Page settings → Search | Title, description, existing slug, inherit/exclude | Reactive snippet/effective URL, Save draft, publication review; draft/live distinction | Changing shop-wide identity, publishing just by saving draft |
| 03 Catalog settings | Existing Product/Category editor; add Collection Search area | Existing title/description overrides; new Collection equivalents | Entity switches in sample only; real Save keeps current lifecycle, defaults/fallback preview | New parallel catalog CRUD, AI writing, invented offers |
| 04 Publish review | Existing CMS Publish action, before write | Content confirmations only | Snapshot-bound blocker/warning list, rerun, go back, confirm publish, conflict/failure states | Fake SEO score, ranking prediction, automated fact verification |

Sample interactions: four navigation entries; page text/snippet edits; page/site policies; catalog entity switches; sample saves + validation; simulated failing/ready checks; three confirmations enabling sample publish; confirmation/cancel and Escape. Existing editor General/Brand tabs explain they are outside this focused sample. No stub is represented as a working application endpoint.

## Acceptance matrix — all pending implementation

| ID | Producer → consumer | Required proof |
|---|---|---|
| SF-P0-01 | Settings → protected API/service → MySQL → shared head | Refresh/second authenticated session consistent; disallowed origin rejected; no unauthorised write; conflict safe; old authored fields retained |
| SF-P0-02 | CMS edit → draft → publish → public HTML | Before publish live unchanged; after publish title/description correct; restore creates draft only; empty fallback/Unicode/escaping; each theme shared head |
| SF-P0-03 | Product/Category/Collection edit → MySQL → PDP/PLP | Override, clear/fallback and refresh work; actual record metadata across all theme layouts, active/draft boundaries; old URLs301 |
| SF-P0-04 | Canonical published document → SSR/pre-render → browser | curl/no-JS main text/H1/links; JS-enabled screenshot matches accepted source, no duplicate DOM; desktop/mobile; current runtime data updates without stale content |
| SF-P0-05 | Publication/catalog/global lifecycle → sitemap/canonical | Correct active home, pagination, tracking/filter rules, XML validity, exclusions, lastmod/invalidation; no chains/loops or draft exposure |
| SF-P0-06 | Route auth/private state → headers/status/cache | Guest/owner/other customer/admin matrix; no private records in sitemap/bootstrap/head/shared cache; existing token-based routes preserved |
| SF-P0-07 | Draft checks → merchant acknowledgement → publish transaction | Blocker denies write, suggestions allow, deliberate noindex publish valid; content change invalidates review;409 stale data, failed checks leave live unchanged; audit actor/revision |
| SF-P0-08 | Approved sample → actual Admin | New controls source-faithful; save/error/loading/empty states, keyboard/focus,390/1536 overflow, Builder/public parity evidence |

Representative content routes: active homepage; CMS service/project/FAQ content; /shop; active category; active collection; direct-purchase/quote/display PDP. All theme layouts get automated shared-contract checks; real browser acceptance current Logistics + a visually different theme in protected preview, with no live theme switching. Private variants: guest/login, own vs other account/order, enquiry/quotation access, admin preview, drafts, removed content.

Not acceptance: Google indexing deadline/rank, AI citations, snippet exactness, leads/sales. Search Console real rendered URL inspection is a later external acceptance gate requiring verified property; unavailable access is recorded, never replaced by200reachability.

## Exclusions / next phases

P1: Organization/LocalBusiness/Breadcrumb/Product/Service JSON-LD, OpenGraph/social media, structured business facts, Search Console/analytics/lead attribution. P2: multilingual URL/hreflang, content automation/monitoring. No llms.txt requirement, ranking score, FAQ rich-result promise or artificial keyword pages in P0. No new paid API, network crawler, arbitrary remote URL fetch, AI rewriting or image search. Retain existing shared renderer and theme contracts.

## Verification ledger

- 2026-10-18: local source inspection/read of master acceptance and relevant skill guidance; prototype only added, application not modified. Browser inventory immediately reports browser use disabled in Work settings. Paused browser acceptance; no terminal browser substitute or auth retries. Screenshot, desktop/mobile visual and actual interactions remain pending. Elapsed time/token usage unavailable.
- Check plan: node syntax check, HTTP200 HTML/CSS/JS and noindex sample marker. These are smoke checks only and do not prove visual fidelity or working controls. No production release requested for this design phase.

- Static smoke result: node --check passed; HTML/CSS/JS each HTTP200 from localhost, no application API exercised. Browser screenshots/interactive/mobile acceptance still pending because Work browser control is disabled. No push or deployment.
