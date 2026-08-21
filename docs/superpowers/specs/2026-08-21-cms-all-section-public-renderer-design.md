# CMS: all sections publish and render

## Outcome

An operator can add any section offered by **Add Section**, edit it, save it,
preview the saved draft, publish it, and see the same published snapshot at
`/pages/{slug}`. This is an `OPERATIONAL_CLOSURE` P0 CMS correction.

There must be no customer-facing distinction between the 10 currently marked
renderer-capable types and the remaining 16 types.

## Evidence and root cause

- `AddSectionModal.tsx` supplies `isVisible: true` for every one of the 26
  section templates. That is the correct default for a newly added section.
- The shared `SectionRenderer.tsx` has a renderer branch for all 26 types and
  `PageSections` is already used by the saved preview/public React entry.
- The canonical capability passport in `resources/cms/page-contract.v1.json`
  still marks 16 types as `renderer.capable: false`. `PageDocumentContract`
  therefore blocks publication before the customer renderer can be reached.
- The existing public route remains under staged cutover. It must consume a
  published canonical document without replacing legacy page behavior.

The defect is a stale capability/release boundary, not a request for a new
Google AI Studio surface. UI level is L0: preserve the current source DOM,
classes, copy, responsive behavior, and interactions.

## Boundary decisions

| Boundary | Decision |
|---|---|
| Convergence mode | `OPERATIONAL_CLOSURE` |
| Scope | All 26 Add Section types; draft preview and published public rendering |
| Runtime truth | MySQL `pages` and `page_documents`; published snapshot is the public truth |
| Admin ownership | Operators add/edit/remove/reorder sections; `isVisible` remains an operator show/hide control |
| New-section default | All 26 templates remain `isVisible: true` |
| Existing hidden sections | Stay hidden; no mass publication or data migration |
| UI authority | Existing Google AI Studio-derived `SectionRenderer`; L0 logic only |
| Lifecycle | Draft autosave -> saved preview; publish creates immutable public snapshot; archive/unpublish removes public eligibility |
| Release surface | Existing `/pages/{slug}` route dispatches canonical published documents to the shared public renderer and preserves legacy fallback behavior |
| Cutover | The official route changes only after every type passes the browser/public matrix |

## Capability contract

For each type below, the closure row is currently `BACKEND_CONTRACT_GAP`: it
is offered in the Admin UI and has an existing shared rendering branch, but
the capability passport blocks publication. The implementation must prove
runtime safety first, then set `renderer.capable: true`.

`hero_slider`, `split_hero`, `announcement_bar`, `media_text_split`,
`timeline_story`, `stats_counter`, `featured_deal`, `pricing_table`,
`video_embed`, `before_after`, `instagram_feed`, `icon_list`,
`comparison_matrix`, `store_locator`, `testimonials_slider`, `logo_cloud`.

The other ten already-open types are regression members of the same matrix.

Each completed row must prove:

```text
Add Section (existing UI, visible by default)
-> protected Admin draft API and MySQL page document
-> saved preview shared renderer
-> publish validation and published snapshot
-> official /pages/{slug} shared renderer
-> desktop and mobile browser evidence after refresh
```

## Implementation design

1. Create a manifest-driven 26-type renderability test matrix. It will build
   canonical valid documents from the existing all-sections fixture, exercise
   saved preview and public runtime payloads, and render each type through the
   shared public `PageSections` path. Types that need real catalog/form facts
   use existing Laravel runtime adapters and form contracts, never mocks.
2. Repair only failures revealed by that matrix: missing public-safe runtime
   facts, invalid default data, public interaction behavior, or mobile-safe
   rendering. Reuse existing SectionRenderer markup; no visual redesign.
3. Change each verified manifest type to `renderer.capable: true`. Publication
   keeps legitimate business validation: malformed document data, unavailable
   referenced catalog data, and invalid coupon/form facts still give stable
   actionable errors.
4. Add an official-route dispatcher at the current `/pages/{slug}` boundary:
   published canonical CMS documents use `StorefrontPageController`; legacy
   page records continue through the existing `StorefrontController` behavior.
   Drafts, archives, and unpublished canonical pages remain unavailable
   publicly.
5. Add end-to-end browser evidence for the complete user journey and retain
   the current Preview, publish, public-route, authorization, unpublish, and
   mobile regression coverage.

## Acceptance criteria

- All 26 Add Section types are `renderer.capable: true` only after their
  shared public render test passes.
- A fresh section starts visible, survives refresh, displays in saved Preview,
  publishes without a renderer-capability error, and renders on `/pages/{slug}`.
- Preview uses saved draft; public route uses only the published snapshot.
- The customer page never receives Admin-only data, hidden sections, draft
  content, private product fields, or internal operational records.
- Existing source UI remains unchanged except for dynamic data binding.
- Desktop and mobile browser tests cover every type, with no console errors or
  horizontal overflow.

## Explicit non-goals

- No Google AI Studio redesign or new visual surface.
- No automatic change to historical `isVisible: false` values.
- No weakening of validation for missing real catalog/coupon/form references.
- No Marketplace, reporting, AI-support, real payment, or logistics work.
