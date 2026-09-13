# Basic WMS online development candidate

Prepared on the existing local main checkout at C:/xampp/htdocs/jwstudio-ecommerce, port8500. Base commit b537a9cb34c670f86ba63037d8cf3e0dbd21ab6a. Candidate changes are not yet committed, pushed or deployed. The full three-phase goal remains active.

## Proposed synchronization

Commit this reviewed Marketplace batch and current Admin assets to main, push to the existing GitHub repository, deploy that exact commit to /apps/jwecommerce, apply the two additive September12 publishing migrations, clear cached configuration, and verify the identical SHA plus authenticated HTTPS routes. Continue concentrated online development acceptance there.

Changed application/test/build files are enumerated in basic-wms-release-candidate-files.json with content hashes. Do not include tmp/, output/, credentials, private source ZIPs or unrelated untracked preview builds. The candidate adds publishing source snapshots and image receipts; migration up methods do not reset inventory or orders. Do not run destructive migration rollback as a deployment step.

## Included behavior and available evidence

- Existing Basic WMS: separate JW legacy/platform snapshot/physical sources; first shared or independent configuration; warehouses and store bindings; count/inbound/outbound/adjustment/safety; authenticated employee/server time/reason/before-after ledger. Browser/MySQL evidence for both modes, first mapping, warehouse creation and binding is recorded in basic-wms-release-blockers.md and current-marketplace-goal.md.
- Formal Admin route and ZIP36 runtime integration, draft import and variant linking. Local browser imported SST-LOW as draft64/variant105, test classification and legacy0, with no inferred physical balance.
- Real Lazada test publishing: browser-created task3 plus service-dispatched task4. Task4/item14986786877 proves explicit all-warehouse zero creation followed by automatic catalog readback and mapping. Earlier comparison item14985673497 remains a marked test item with provider800; it is not physical WMS stock and has not been silently altered.
- Shopee code path: source-owned images and variants, fresh category/brand/logistics preflight, durable parent then model initialization, original-task recovery, targeted catalog readback and confirmed identity mapping. The formal three-step modal now calls this adapter. This remains fixture-tested until online sandbox authorization and provider acceptance.
- Latest backend regression:399 tests2442 assertions. TypeScript and current Admin build pass. Focused frontend submit/session/modal/dependency/store-switch checks pass. Current main asset main-CuFfsygc.js, InventoryRuntime-DVgO2F1w.js. Existing bundle-size warning persists.

## Online acceptance and remaining limitations

- Shopee OAuth must use the approved existing HTTPS deployment. Local APP_URL and online callback intentionally do not match. Do not create a tunnel or transfer codes between databases. Official console login recovered; test partner1232619, shop227482706 and prior test product846055463 remain available. Sandbox key is accessible in the console but not yet saved in local environment configuration; do not substitute live partner2033707 credentials.
- Verify both formal Connect Store/result flows, Shopee catalog read/publish/model mapping, and bounded stock writes with designated authenticated test stores. Lazada official-test API access is not proof of real merchant Seller OAuth.
- Shopee searchable-only attributes and multiple custom values still need fuller controls; imported external Shopee images need supported preparation. Provider-specific category/GTIN/volume restrictions may require follow-up fixes during actual acceptance. Do not label all possible categories or production sellers operational.
- Production stock broadcast, automatic order deductions/reservations/takeover, transfers, advanced reconciliation/mode conversion and fulfillment remain server-disabled. Publishing/test writes stay default-off and are enabled only for explicit designated test exercises. Basic WMS remains independently enabled and does not take over legacy stock or orders.
- This release is the online development candidate for concentrated Phase1 acceptance. It is not a declaration of Phase1 completion or permission to enable Phase2/3 operations. Complete remaining defects under the same scope after online verification; do not discard prior evidence or reopen UI design.

## Approval boundary

Read-only Hostinger preflight confirms current deployed SHA remains the base above, no tracked source changes, and only an existing private environment backup plus RELEASE_COMMIT are untracked. Basic UI/service flags are true; advanced/transfer/accounting/broadcast/sandbox-write/publish/fulfillment flags are false. Neither new publishing migration exists there yet. IMPORTANT: effective online Shopee environment is sandbox but partner_id is live2033707, so callback readiness alone is insufficient. Correct to test partner1232619 and its test key before sandbox OAuth. The known September6 server environment backup contains test1232619 credentials; these were recovered safely to local .env under dedicated SANDBOX variables without displaying values, retaining live credentials. Local environment now selects sandbox. Remote environment remains unchanged pending synchronized release/online acceptance.

Synchronization approval is required by project AGENTS.md before committing/pushing/deploying this batch. The user should approve the exact candidate above, including its additive migrations and online acceptance. No synchronization has occurred merely because this document exists.

Final credential preflight: restored local test partner1232619/raw test key produced HTTP302 to the official open.sandbox.test-stable.shopee.com consent host on September13. Diagnostic followed no redirects, created no persisted OAuth state/store and exposed no credential values. This verifies the signed entry only; complete online consent/token exchange remains acceptance work after approval. The approved deployment must correct the online live-ID/sandbox mismatch before that flow.
