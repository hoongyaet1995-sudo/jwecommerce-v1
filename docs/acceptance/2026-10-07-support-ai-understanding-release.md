# Support AI understanding / clarification release — 2026-10-07

User synchronization approval: “同步吧”. Scope: understanding contract, role-labelled context, constrained tool selection, contextual clarification, language preference, public tracking entry, sessionless order verification; approved phrase-chip/toggle settings UI and optional-hint labels. No schema migration or dependency change.

Local verification: 251 affected backend tests / 1538 assertions passed (30.51s); 8 frontend tests passed; Admin build passed (12.16s). Real-provider synthetic benchmark covered 18 scenarios across recorded runs and repairs, including staff BILLY context and Malay policy follow-up. See home-ai-quality.md for failure ledger and evidence. No universal accuracy claim. Live channel end-to-end acceptance pending.

Pre-release local / GitHub main / Hostinger HEAD: dcf7b700a4c21edac5ed2ad8f95794c390ab6595. Production root and environment confirmed; no tracked production modifications. Preserve unrelated local checkout/marketplace/inventory changes and historical asset deletions. Stage only selected support source/tests/docs and current manifest-referenced Admin assets; preserve old tracked asset versions.

Limitations: fixed fallback and private-order templates en/ms/zh; other detected languages use English fallback on those paths. No vector indexing, attachment interpretation, pacing or Blast Campaign. Next phase proposed: evidence-grounded business query/answer completeness, partial-result clarification, tool-failure distinctions and employee-facing trace.

Before release verification: rerun SupportChatApi, UnifiedKnowledge, SupportUnderstanding, policy/order/FAQ/provider media regression against selected source; no application edits since accepted run. Generated bundle diff contains intentional Markdown trailing spaces inside a dependency string; source-only whitespace gate will be used without altering generated literals.
- Fresh pre-release backend rerun passed: 251 tests / 1538 assertions, 26.69s. Source-only staged whitespace check passed. Release approvals and selected source reviewed; pending SHA alignment and live HTTPS smoke.
