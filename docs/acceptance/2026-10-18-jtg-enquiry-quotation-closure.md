# JTG enquiry -> follow-up -> quotation acceptance — local only

User selected this next phase after closing technical search readiness. Canonical root C:/xampp/htdocs/jwstudio-ecommerce, HEAD ab2e66a92d96aea4d3f36b7427dae3b7700ed26d. All prior dirty work, including accepted Search pilot, must stay intact. No commit/push/deploy.

Purpose: prove a visitor can select a real active JTG quote product/variant, submit an enquiry, have staff receive/assign/follow up internally, publish a quotation and view the correct immutable version as the owning customer. Preserve current UI. Existing implementation is reused; fix only evidenced gaps.

Boundary: clearly marked local QA enquiry, reserved example.test email, no genuine phone. No new catalog fixtures, live email/WhatsApp delivery, quote acceptance, order/payment/stock/fulfilment changes. Quote publication on a test receipt is authorized local application state. Support/AI conversation linking is excluded from this minimal path unless a demonstrated dependency requires it.

Mapping:
- Public PDP/form -> ProductEnquiryService -> product_enquiries/events -> session-owned receipt + protected Admin enquiry hub.
- Admin assignment/status/note -> optimistic versioned service -> same enquiry/event ledger -> persisted detail/reload; notes never in public receipt.
- QuotationEditor -> EnquiryQuotationService -> group draft + immutable sent version -> private customer quotation document; revise never rewrites v1.
- Permission checks: anonymous non-owner cannot read receipt/quote; unauthenticated Admin API denied; unrelated catalog/stock/orders data must retain hashes.

Initial scope status: existing source found, not yet reaccepted for this JTG journey. Next run: focused ProductEnquiryTest, ProductEnquirySupportTest, EnquiryQuotationTest to establish current backend contracts before browser acceptance. Isolated SQLite only, no application changes yet. Unexpected issue counts for this phase start0; historical module ledger issues remain recorded in their original documents. Time/token usage unavailable.

Initial run:21pass/1fail,289assertions. Issue Q-CSRF-EXPECTATION count1: legacy HTML quote-send expectation419 predates accepted Admin expired-session303 recovery (bootstrap/app.php76-80; prior expiry ledger). Backend rejects token as intended. Update test to expect303+login+expiry flash for HTML,419 for JSON, zero sent versions after either invalid request, then valid-token success and duplicate-publication idempotency. Application auth/CSRF code unchanged. Next run same22tests plus AdminSessionExpiryTest for the shared contract, justified by this test update.
- Test-path diagnostic1: expiry test is not at tests/Feature/AdminSessionExpiryTest.php; invocation stopped before tests. Enumerate exact file path before corrected focused run; no application failure.
- Corrected26test run passed315assertions. Browser actual QA enquiry ENQ-20261018-EOS3M3R7Y7 /1121fc7b-1bc3-4ad5-9f1d-926dd6aab0f7 created from product118 variant188. Follow-up assigned_to2 /JW Studio Owner and note persisted (MySQL confirms). Early reload selected label transiently showed Unassigned before staff options loaded; persisted owner is2, no data loss. Do not treat transient loading label as failed persistence.
- Draft quotation QT-20261018-9NUYPPOO saved/reloaded MYR210000minor (2x1000 +100), draft absent from customer receipt. Preview confirmation explicitly says publish to enquiry/no email. Confirmed browser publish v1, customer receipt now exposes private version9d8a8731-d945-4b09-a251-b41e6777d01c. No support link created.
- Browser diagnostics: frame-tree timeout1 recovered through screenshot then current AX; duplicate Send quotation locator1 resolved by scoping observed #send-dialog. Neither caused repeated publication or application changes. Next verify actual customer quote, revision isolation, owner/private headers and preservation hashes.

## Final local closure — 2026-10-18 Asia/Kuala_Lumpur

| ID | Status | Evidence |
|---|---|---|
| JTG-EQ-001 | CLOSED | Actual PDP quote modal product118/variant188 -> one guest enquiry -> receipt and protected Admin same reference/SKU. |
| JTG-EQ-002 | CLOSED | Status Following up, assignee JW Studio Owner/id2, internal note persisted across reload and final browser read. Customer receipt/quotes contain no internal marker. |
| JTG-EQ-003 | CLOSED | Saved/reloaded v1 draft MYR210000minor; invisible on customer receipt until explicit send confirmation. |
| JTG-EQ-004 | CLOSED | Published v1 private customer page210000minor; created v2 draft230000minor; v1 stays210000 both before and after v2 publication; immutable v1 document hash unchanged. Customer receipt exposes both sent versions. |
| JTG-EQ-005 | CLOSED | Separate no-cookie HTTP client gets404 for receipt/v1/v2 and401 for protected Admin API. Focused26tests315assertions cover ownership/idempotency/version/currency/expiry and Admin HTML303/JSON419 rejection. |
| JTG-EQ-006 | CLOSED | Existing enquiry/event/quote/version rows all retain original hashes. Only1QAenquiry,1quote group,2sent versions,10events added. Products46, variants53, orders31, page_documents32 and Search entries/tasks/refreshes unchanged. |

Final customer v2 quotation UUID6092c69e-78af-48b9-95f1-d6b5cf3c4bf9, total230000minor. Current enquiry following_up/assigned2, support_conversation_id null. No quote acceptance/order/payment/booking/stock deduction or external email/WhatsApp delivery performed. Test amounts are clearly labelled QA, never an approved commercial quote.

Final browser notes: native snapshot timeout cumulative2; switched to scoped read-only DOM observation, no action repeated blindly. Native option wait locator diagnostic1 corrected using actual selected-field read after fresh AX. Final actual owner label JW Studio Owner matches MySQL2; no persistence defect. No application issue reached3failures. Final customer v2 and Admin detail console warning/error arrays empty; customer desktop scrollWidth1528/clientWidth1528, no overflow. No new mobile acceptance claim (no UI/CSS changes in this phase).

Application/UI changes this phase:none. Only EnquiryQuotationTest updated for the already accepted expired-session behavior, plus acceptance/evidence docs. No frontend build needed. Original Search pilot and all unrelated source changes retained; no commit/push/deploy. QA receipt/quote and Admin follow-up tabs retained for review.

Evidence folder: C:/Users/User/Documents/Codex/2026-10-18/cua-repl-chrome-hoongyaet1995-gmail-com/outputs/jtg-enquiry-quotation. Data snapshots, permission-checks.json, data-preservation.json, browser-follow-up-verification.json and screenshots are local-only QA evidence. Overall elapsed/token usage unavailable; suite duration6.71s measured by runner.
