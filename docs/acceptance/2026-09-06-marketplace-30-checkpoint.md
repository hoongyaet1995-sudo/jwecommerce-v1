# Marketplace 30 implementation checkpoint

## Scope and source

- Development: `C:/xampp/htdocs/jwstudio-ecommerce`, branch `codex/marketplace-oauth-catalog`.
- Source: `jw-ecommerce-dashboard (30).zip`.
- SHA256: `767ED21BE2485ABF70A158B3B04477B4DB10D06B746AE6C31FAC3F854B134F1A`.
- Scoped extraction: `resources/admin/src/previews/marketplace30` (Marketplace components, common navigation/toasts and pruned context/types/fixtures).
- Isolated entry: `http://127.0.0.1:8532/marketplace30.html`. All data and authorization there are simulated; official Admin entry is unchanged.

## Implemented local backend protection

- Store-scoped locking and API rejection of duplicate same-store JW variant mappings; cross-store reuse retained.
- Migration adds backfilled store identity, unique store/JW variant constraint and composite listing/store foreign key.
- Migration rejects legacy conflicts before DDL rather than deleting records.
- Local MySQL migration succeeded. Isolated feature tests: 9 passed, 43 assertions.
- Tests cover direct duplicate inserts, forged store identity, legacy conflict refusal and nonconflicting data preservation.
- Real concurrent MySQL request acceptance remains pending; SQLite tests are not that evidence.

## Preview validation

- Admin TypeScript lint and isolated Vite production build passed.
- Browser clicked Mapping -> Connect Store -> connection modal.
- Screenshots: `output/playwright/marketplace-30/connect-desktop.png` and `connect-mobile.png`.
- Desktop modal renders. At 390 x 844 the modal extends beyond the viewport, clipping its title and bottom actions. Mobile acceptance fails pending an approved minimal responsive correction.
- Browser console had one favicon 404; no runtime exception observed in this tested path.
- Full source-app screenshot comparison and remaining interaction matrix are not complete; no pixel-parity completion claim.

## Source gaps before production binding

- In-app simulated provider authorization, fake token generation and fixed token lifetime must not enter production.
- Static security/quota/webhook metrics are not verified capabilities.
- Developer configuration panel and secret-copy controls do not belong in merchant production UI.
- Hub consumes `triggerChannelSyncAll` absent from the source context. Extracted preview leaves it undefined/inert; it is not a working sync implementation.
- Store identity must come from verified provider responses, not the typed handle.

## Next gates

### Approved mobile correction

User approved the minimal responsive correction. Only ConnectStoreModal's container classes changed: viewport-bounded maximum height and internal vertical scrolling. No DOM, copy, colors or authorization behavior changed. Browser assertion reproduced the original title at y=-56.75; after correction the title is accessible and the bottom action scrolls into the 390x844 viewport and passes a Playwright trial click. Desktop 1280x800 title/action bounds also pass. TypeScript lint and isolated Vite build pass (existing chunk-size warning remains). Evidence: `output/playwright/marketplace-30/connect-mobile-bottom-fixed.png`. This resolves the specific clipping blocker, not the remaining full fidelity/OAuth acceptance gates.

1. Approve minimal modal max-height/scroll correction or obtain corrected Google AI Studio source, then repeat mobile and source comparison.
2. Finish source manifest and preview acceptance before official UI cutover.
3. Implement Shopee server-side OAuth state/token/provider transport and catalog jobs, then real authorized seller acceptance.
4. Complete remaining roadmap phases in order. No real authorization, publishing, fulfillment, stock writes, GitHub push or Hostinger deployment was completed by this checkpoint.
