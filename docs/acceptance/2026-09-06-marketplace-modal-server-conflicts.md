# Server-backed Mapping modal conflicts — local checkpoint

The ZIP 30 Mapping page in server-pagination mode now supplies a stable conflict-check callback to the original modal. The callback reads persisted same-store occupancy from the existing mapping-context endpoint, not just the visible catalog page.

Confirmation stays disabled until the selected SKU receives an explicit successful eligibility response. Missing candidates, request errors, stale responses and pending checks do not authorize saving. Conflict details reuse the source conflict banner. The final save still requires the backend transaction/unique constraints; this preview check cannot replace them.

Evidence: three Vitest files, five tests passed, including an occupied SKU absent from the visible page and an empty response rejected as unverified. TypeScript and `git diff --check` passed. No remote calls or deployment performed.

Remaining: mount the operational Mapping context with safe action capabilities, complete browser acceptance and release approval. The official Mapping runtime is not yet switched to this source page.
