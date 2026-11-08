# Existing Marketing Media Reuse Implementation Plan

**Goal:** Select existing library image/video in Posts and Video Studio without browser download/upload. User authorizes local implementation and acceptance only, no commit/push/deploy.
**Spec:** Current conversation, preserve existing selectors/DOM/style; library register and authorization remain authoritative.
**Architecture:** Posts accept library:UUID alongside unchanged legacy media keys and resolve it at draft, snapshot and publication. Video Studio imports a verified source server-side into the existing project-upload contract, with an idempotent DB lineage link. Revalidate linked source permissions/hash/approval at reference resolution, read, production planning and execution entry points. No publishing/generation/approvals triggered by selecting.

- [x] Tests first: usable candidate image reference in Posts, owner isolation, rejected unapproved film, cross-owner denial, project import no upload/provider call, repeated import, origin revocation and tamper stops linked reuse.
- [x] Add marketing_media_reuses lineage table, MarketingMediaReuse service for resolve/list/import/assertLinked. File-copy only inside private source/destination verified hashes; preserve source files. Revalidate copies recursively against canonical origin; reject cycles. Avatar published/version and archive/revoke boundaries retained.
- [x] Extend existing Posts picker with Content Library/Product images and type filters. Preserve legacy keys and publisher contracts; add library resolver branch to common media path and protected preview controller.
- [x] Add Choose from library buttons in current Video Studio upload/add/edit panels, preserve consent and manual/AI scope controls. New protected endpoint delegates to same validation/project upload lifecycle. Save chosen reference, do not auto-generate or publish.
- [x] Apply only new migration locally. Run focused regression and authenticated browser save/refresh proofs for image/video, picker search/pagination, revocation safety. Record exact failures/changes in ledger. Preserve all unrelated dirty work.

Canonical8500 Video Studio browser gate remains blocked by the pre-existing expired production rate card. Independent8501 billing-off/live-off MySQL acceptance demonstrated image/video add/save/refresh. No production rate, authorization, or .env changes; see phase acceptance record.

Follow-up approved and accepted on8500: pricing preview isolated from pure read/free manual saves, paid quote/consent/expiry retained, explicit manual replacements persisted, fixed picker footer.174PHP+24JS tests pass; real MySQL/browser evidence in phase record supersedes initial8501-only limitation. No release.
