# WWW Relaxed default release — 2026-11-15

Approved scope: GitHub main + www only. ecom code and data must remain unchanged. No unrelated dirty source or local QA documents are released.

Source baseline99381f2a. Three frontend default changes plus focused regression; bounded PHP release service/CLI/test/frozen17-page34-document plan. Runtime defaults and connected template factory become Relaxed1.1s; explicit Smooth stays0.8s, None/reduced motion stay static. Page Settings identifies its layout default.

Current production snapshot:17published pages123Sections/10static;34draft+published documents. OldHome3 and CustomWorkflows14 contain unpublished draft differences. Patch each stage independently; never call publication service. Only style.motionPace changes where page style already exists (28documents). Six legacy documents keep their original JSON and inherit the new1.1s runtime; no synthetic palette. All Section arrays stay unchanged. Homepage11, menus, settings, other pages preserved. Optimistic pre/post document hashes+versions, scope/root guards, private backup, transaction, protected-row hash and idempotence required.

Canonical local acceptance:17QA copies saved/refreshed, all123Sections scrolled,113actual1100ms/10static, mobile390Home/Commerce/oldHome nooverflow. InspectorSmooth800 save/reload followed by inherited1100 verified; intendedhashrestored.

Exact isolated release:19frontend tests PASS;38PHP tests/297assertions PASS; full TypeScript0diagnostics; Vite+SSR build PASS (Vite13.33s, existing chunksizewarning). Scoped diff clean. Initial intentional TDD red3missingclass then green. No unexpected product failures in this release phase.

Deployment: fast-forward www only, cache clear, guardedCLIcheck/apply and repeat; private backup under storage/app/releases/www-relaxed-default-20261115/before.json. Postrelease acceptance must confirm same GitHub/release/www SHA; ecom remains0f26bc6a/Home33 and row hashes unchanged; all17public pages/123Sections use intended durations with10static preserved.

Evidence: output/jw-cms-integration-20261114/relaxed-release-20261115. Totalelapsed/tokenusage unavailable.
