# Support AI V2-B local checkpoint

Plan: docs/superpowers/plans/2026-10-07-support-ai-v2-memory.md
Spec approved, implementation plan approved. Baseline5aa7bce, branch codex/social-crm-identity. Unrelated existing changes preserved; canonical local checkout required by user, no new worktree. No release authorized.

Ruling: preserve trusted association evidence explicitly when CRM resolves a trusted contact; pre-existing associations without evidence remain same-identity only. Cost: conservative cross-platform continuity until new trusted event.

Task1 initial intentional TDD red: php artisan test --filter=SupportCustomerMemoryPolicyTest. Expected missing policy/model. Unexpected failure counts0; elapsed/token usage unavailable.
- Initial red confirmed missing classes2tests. Task1 next run after model/policy/settings implementation: same command; expected pass.
- Task1 green2tests7assertions. Task2/3 intentional red php artisan test --filter=SupportCustomerMemoryFlowTest: missing service/extractor expected; count separate from unexpected errors.
- Initial flow run hit distinct test-fixture schema issue (missing social columns), unexpectedcount1. Added minimal matching columns to test fixture. Rerun focused3 newtests to establish genuine missingservice red.
- Flow intentionalred confirmed missingservice; schema issue resolved. Next focused3tests after service/extractor implementation.
- Task2/3 base flow green3tests14assertions; added queued capture and controls. Next policy/flow/regression command after integrations checks disabledpath unchanged.
- Regression74tests503assertionspassed11.08s. Next new staffaccess/crosstrust/failure/pruning tests after endpoint implementation; tests reinforce integratedbehavior. No standalone intentionalred for these additional coverage tests (implementation already exists).
- Build passed14.69s; prior ledgerappend was wrong-relative-path and corrected here. Next frontend test command verifies defaultoff and retentionbounds.
- Browser4 local tab connection refused; old server stopped (environmentblocker), starting canonical artisan server. No repeated auth attempts. Frontend test1passed. Next apply only new migration locally.
- Next synthetic actualmodel extraction via php tmp/memory-acceptance.php, no real customer records transmitted, no platform send. Synthetic conversation human_mode.
- Review lifecycle defect: historical staff edit blocks all later customer corrections. New regression intentionalred php artisan test --filter=SupportCustomerMemoryFlowTest::test_new_customer_statement before fix.
- Review fixes: allow future explicit correction, compare staff state around extraction, recheck master/channel, permanently close issue boundary, guard panel responses. Next focused four regression tests; new race coverage added after implementation, not initial TDD red.
- Four lifecycle/race regression tests passed14assertions2.74s. Next full scoped backend regression, frontend controls/card tests and fresh build after UI guard/label changes.
- Second review identified old preference resurfacing after deletion. Intentionalred: php artisan test --filter=SupportCustomerMemoryFlowTest::test_deleting_latest before selecting tombstones as supersession winners.
- Tombstone initialred confirmed2.55s. Retrieval now selects newest perkind including tombstones, then checks eligibility; three bounded queries avoid unbounded history/N+1. Next targeted memory regression plus typecheck comparison.
- Prior edit failed before changing PHP due Python default Windowsencoding. Subsequent test was unintended unchanged rerun: unexpectedcount1 for tombstoneissue; corrected explicitUTF8 and shell failfast. Now bounded retrieval actually applied. Next same targeted regression.
- TypeScript125baseline/current errors, no newdiagnostics. Next actualprovider synthetic return conversation ambiguous greeting language and negative extraction; no channel sends, human_mode synthetic only.
- Synthetic actualprovider returnhi recognized Malay (ms), two historicalcontexts and negativeextract0; evidence output/support-ai-v2-memory-provider-evidence.json. Browser edit persisted and cancellation retained content. Next final scopedbackend after tombstone retrieval fix.
- Final backend89passed555assertions12.10s. Fresh review found no remaining important defect within identity/lifecycle/race scope. Scoped diffcheck revealed trailing EOFblanklines from edits; removed only trailing blanklines, next diffcheck verifies whitespace.
- Browser settings retention31 survivedreload; restored30 and memorydisabled confirmed from canonicalservice. Final two new checks cover ingress queue/job consumption and private settings persistence/unsafe ranges; next full scoped regression verifies these added boundaries.

## Local verification checkpoint

- Timestamp: 2026-10-07 MYT. Baseline HEAD5aa7bceaf6a18c46f7aa43e3ac4877c508fde6e0; changes remain uncommitted. No GitHub/Hostinger synchronization authorized.
- Implemented: encrypted typed memory, trusted identity scope, asynchronous dedicated support queue job, exact source extraction, bounded reply context, staff edit/delete endpoints and source/expiry card, Knowledge opt-in, Guardrails7–90day retention, daily derived-content cleanup.
- Explicit later customer corrections supersede earlier ones; deleted/expired latest memories do not resurrect older versions. Concurrent staff corrections and master/channel disablement are rechecked before persistence. Resolve/archive stores a message boundary and clears only unresolved memory.
- Final backend command php artisan test --filter='SupportCustomerMemory|SupportFaqTest|CustomerContactResolver':91passed566assertions12.16s. This includes inherited existing SupportChatApiTest coverage. Extraction, retention and lifecycle tests are consolidated in SupportCustomerMemoryFlowTest rather than separate planned filenames.
- Frontend controls/card tests2passed3.70s; current build passed15.62s. TypeScript baseline125/current125, no introduced diagnostics. Scoped diffcheck passed after EOFblankline removal. Existing bundle-size warning and baselineTypeScript issues are not changed by this phase.
- Actual provider: synthetic explicit language/product preference captured; new recognized conversation plus hi selected ms from historical language context; neutral negative statement extracted zero memories. Evidence output/support-ai-v2-memory-provider-evidence.json. No real customer history sent, no platform message sent.
- Browser: authenticated local Knowledge toggle and Guardrails31days persisted after refresh; restored enabledfalse/retention30 confirmed from service and refreshedUI. Synthetic staff edit persisted on refresh; deletion confirmation and cancellation verified. Actual deletion/replay suppression covered by protected API and component tests; no claim of completed live-platform memory acceptance.
- Screenshots: output/support-ai-v2-memory-local-dark.png and output/support-ai-v2-memory-settings-off.png.
- Fresh independent review: original five lifecycle/race findings fixed; no remaining important issue found within reviewed scope. Synthetic memory stays encrypted/local, no historical backfill. Unrelated checkout/marketplace/docs/assets changes preserved.
- Unexpected failure accounting: fixture schema issue1resolved; tombstone issue1unexpected unchanged rerun after failed encoding edit, resolved. Intentional initial reds tracked separately. Historical accounting before this phase unavailable. Total elapsed time/token usage unavailable; only measured command durations reported.
- Open release gates: approved synchronization; exactSHA release including migration/queue restart; production daily support:prune-customer-memories cron; real channel return-visit and trusted cross-platform acceptance after release. Bounded V2-B local implementation verified; fullV2 not declared complete.
- User approved synchronization. Release preflight: local/GitHub/Hostinger base5aa7bce; serverroot verified /home/u762348670/apps/jwecommerce, productionPHP8.2.33/debugoff; unrelated untracked remote backups untouched. Shell crontab unavailable (environment), use approved hPanel Cron Jobs UI for dedicated cleanup task. Next fresh scoped backend/frontend tests before accepted commit.
