# Support reply cadence local checkpoint

Plan: docs/superpowers/plans/2026-10-07-support-ai-v2-reply-cadence.md. User approved design and native execution.
Baseline: codex/social-crm-identity, HEAD 2e987e4e3f3981070f085e72174f714a06bba690. Existing memory-intent repair and unrelated checkout/inventory/docs/build changes preserved.

Ruling: use the explicitly required canonical checkout, scoped changes and this durable ledger instead of an isolated code checkout; no commits/push/deployment before synchronization approval. Skill bookkeeping shell scripts are not required to alter the user's Windows environment.
Pre-flight: task 1 supplies replyCadence config and encrypted batch model to tasks 2–4. Task 2 prepares output for task 3. Task 3 must reuse the existing source/settings/control transport guards. No contradictory interfaces found.
Ruling: store candidate segments alongside one validated answer; accept only exact ordered text reconstruction, otherwise one safe segment. Safety/handoff/verification bypass segmentation and spacing.

## Issue ledger

- Initial task 1 TDD red: `php artisan test --filter=SupportReplyCadencePolicyTest`, expected absent policy; not an unexpected failure.
- Time/token accounting: tokens unavailable. Command timings are recorded only when measured.

## Remaining gates

Backend, frontend, build, localhost browser/queue acceptance and independent review pending. No new release authorized. Existing Facebook memory issue remains pending live reacceptance.
Task 1 policy green: 1 passed/2 assertions/1.38s. Initial task 2/3 TDD red: php artisan test --filter="SupportReplyCadenceTest|SupportReplySegmenterTest"; expected missing services. No provider sends. Attachments keep their existing analysis/confirmation path; coalescing operates on customer text, preserving attachment security boundary.
Task 2/3 initial red: 6 expected missing-service failures, 54 inherited regression passes/381 assertions/6.56s. Implemented encrypted batches, source/control guards, delayed jobs, lossless candidate segments and default-off route integration. Pre-run php artisan test --filter="SupportReplyCadence|SupportReplySegmenter"; purpose initial pipeline green after concrete implementation.
Pipeline initial green 61 passed/406 assertions/6.54s. Task 4 declared missing-controls initial red observed, 1.52s. Ledger append initially used resources/admin-relative path and failed once; corrected to absolute path, diagnostic accounting retained. Queue timeout110 < retry120 and lock115; interrupted generations fail visibly rather than rebill.
Pre-run initial TDD red for pending-before-provider reservation: php artisan test --filter=test_unconfirmed_provider_send_is_pending_before_reservation_and_stops_batch_without_replay. New UI consistency requirement: never label a queued social draft sent before transport starts. Other boundary tests added for six cancellations, queue-route integration, private staff-only batch evidence and settings save bounds.
Pending-before-provider initial red confirmed 1.48s. Changed queued social segment status to pending and explicitly allowed the existing provider reservation to claim pending AI records. Pre-run coherent backend cadence + FAQ + Messenger + WhatsApp guard regression after these edits.
Backend boundary suite green:111 passed/708 assertions/11.28s. Ruling: start the social collection window at provider ingress, not when a backlog worker happens to run; added direct enqueue to both existing ingress paths while preserving default-off/attachment behavior. New fake-HTTP pending-reservation test added. Read-only guessed-file lookups produced path errors; use rg file inventory for subsequent reads (Attention: cumulative path lookup issue at least3, historical exact count unavailable). No application test unexpected failures so far. Pre-run broader backend suite and frontend lint/build after ingress/visibility/type changes.

Broader backend green304/1858assertions33.09s; frontend build green11.36s. Full TypeScript check failed: new missing replyCadence API response type identified and added; other preexisting errors outside cadence remain. Type issue cumulative1. Local MySQL migration failed Invalid default deadline_at (cumulative1); SQLite missed MariaDB timestamp default behavior. Changed required scheduling columns to DATETIME, preserving model datetime casts. Pre-run same targeted migration after concrete schema correction.

Independent reviewer P1 unanswered-input loss during generation; P2 afterCommit queue insertion crash gap. Declared intentional red: test_new_input_preserves_unanswered_generation_sources, expected first_source_id mismatch. Repair scope carry cancelled unanswered inputs and atomic same-DB beforeCommit insertion; no provider calls. Migration corrected green56.78ms.

Review fix green66/437assertions7.14s. Follow-up reviewer P1 pre-enqueue cancellation race, P2 staff max first-source ambiguity. Declared intentional red test_input_cancelled_predecessor_is_recovered_before_enqueue before repair. Local index adjustment tinker PowerShell quoting failed once; fixed via bootstrap PHP script, index aligned successfully.

Follow-up review green67/441assertions7.15s. Preflight resolved action URL payload overflow: intentional red test_action_url_overflow_fails_before_any_segment_is_published, expected ready versus failed. Prevent sending earlier segments before discovering final-segment overflow.

Overflow green attempt produced1unexpected failure (306passes1868assertions31.27s): inherited SupportChatApiTest setUp mocks resolver to return no actions. Concrete diagnostic tests/Feature/SupportChatApiTest.php173. Correct overflow fixture binds real resolver; rerun targeted overflow then coherent regression. Application overflow issue unexpected count1 (fixture), no blind rerun. Browser actual model returned one complete grounded answer; local Storefront switch baselineOFF, temporarily enabled for cadence test, restoreOFF at end.

Overflow corrected fixture green1/4assertions1.57s. Native real model initial longer answer kept one bubble. Ruling use answer topic paragraphs plus segment_paragraphs flag to avoid duplicate output tokens under saved token cap; exact reconstruction and fallback retained. Script encoding cp1252 read failed once, no source mutation; corrected UTF8. Pre-run generation test and coherent regression after output-contract change.

Review follow-up found recovery must consider latest predecessor overall, not search past completed batches. Declared intentional red test_completed_replacement_prevents_old_cancelled_input_replay. Also handle generation_superseded cancellation race; same settings/control eligibility required.


## Local checkpoint at 2026-10-07 23:56 MYT

- Backend final coherent suite: `php artisan test --filter='SupportReplyCadence|SupportReplySegmenter|SupportUnderstanding|SupportCustomerMemory|SupportFaqTest|MessengerReplyTest|MessengerInboundTest|WhatsAppSupportTest|SupportAttachmentAnalysis'` -> **308 passed, 1872 assertions,31.49s**. Full output `output/cadence-regression-final.txt`.
- Frontend controls + saved-normalization: `npx vitest run src/components/ai-auto-reply/AIReplyCadenceControls.test.tsx src/data/normalizeAIAutoReply.test.tsx` -> **5 passed,2files,1.56s**.
- Admin build succeeded **11.31s**, `output/cadence-build-final.txt`. Full TypeScript check remains failing on existing repository errors; new replyCadence response type error resolved (no replyCadence/AIReplyCadence diagnostics in final type output). Do not describe full typecheck as passing.
- Local MySQL migration succeeded after required schedule fields changed TIMESTAMP->DATETIME. Local unique index aligned to latest_source_id, same as source migration; no production migration performed.
- Authenticated localhost Personality page: default OFF; enabled and saved, refresh retained2s/1s/max3; disabled saved afterward. Light/dark screenshot evidence `output/cadence-settings-light.png` and `output/cadence-settings-dark.png`.
- Real localhost model + actual database support queue: first longer two-topic answer preserved as one complete bubble. Three-topic request then produced three complete grounded bubbles (delivery,returns,damage), batch0cbde39d-e99e-4764-86ba-e6ef3ba6a1fe completed3/3. Messages476/477/478 timestamp15:52:40/41/42 UTC, each sent; one-second spacing. `output/cadence-live-local-evidence.json`, storefront screenshot `output/cadence-storefront-three-segments.png`.
- Employee Customer details shows Reply Delivery confirmed count; screenshot `output/cadence-delivery.png`. Public response does not expose internal batch or failure metadata.
- Original local Storefront channel OFF and rhythm OFF restored; master and social switches preserved. Zero pending support-ai jobs at evidence check. Temporary local worker stopped after acceptance.
- Fresh reviewer findings fixed with declared failing tests then green: preserve unanswered generation inputs; recover pre-enqueue cancellation races including generation_superseded; avoid resurrecting old cancelled sources past a completed replacement; deterministic current staff summary; atomic same-database scheduling; final action URL preflight and all-or-fail transport composition. Final targeted reviewer reports no additional findings.
- Accounting: unexpected overflow check1 was inherited test resolver stub, resolved with real resolver fixture; migration1 resolved MariaDB schema compatibility; typecheck1 new response type resolved, unrelated repository diagnostics remain. Tool path lookup Attention retained, no blind reruns. Encoding-read and PowerShell tinker quoting one failure each, resolved without secret exposure. Tokenusage unavailable.

## Remaining release/acceptance gates

No commit,push or production release this turn. Ask synchronization approval for coherent cadence plus preserved pending Facebook memory repair. Real Facebook memory/cadence acceptance is pending deployment. WhatsApp Windows App remains pending human acceptance; no WhatsApp Web used. Instagram has transport regression only; TikTok awaiting provider approval. Rapid collection/interruption/takeover/provider ambiguity have automated coverage; browser actual interruption/takeover race not separately exercised this checkpoint. Do not close full cross-platform phase until remaining evidence is recorded.
