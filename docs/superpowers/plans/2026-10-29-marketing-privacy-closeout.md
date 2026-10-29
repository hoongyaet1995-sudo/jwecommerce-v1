# Marketing privacy / checklist closeout
Approved scope: local only; preserve financial/order/enquiry/source records and UI; no release.
1. TDD: choice withdrawal clears source, expired choice cannot collect; new preference is session-scoped and CSRF protected. Rejecting analytics never blocks shopping.
2. Central privacy service: built-in choice validity 180 days, visitor events 90 days, raw redirects 7 days. Legacy explicit session choice receives a finite expiry on first read; expired choices re-prompt without affecting shopping. No legal-compliance claim. Daily local-evidence cleanup command with dry-run; only merchant-scoped expired events, retire visitor session correlation in old attribution snapshots; no delete of orders/enquiries/payments/refunds/links.
3. Existing storefront injection supplies effective choice/collection status; normal footer Privacy choices opens existing-style dialog and accessible toggle, explicit Save/Cancel. Re-enabling does not recover an old rejected campaign token.
4. Report labels activity retention boundary without hiding retained paid/enquiry outcomes.
5. Test isolated pruning/idempotency/foreign merchant, normal local page preference, command dry-run only on existing DB; consolidate current-status section atop both phase checklists and retain chronological evidence.
