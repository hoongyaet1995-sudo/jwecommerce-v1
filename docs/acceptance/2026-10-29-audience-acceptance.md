# Audience integration acceptance

Status: Audience foundation and page-driven Sync released with user approval on 2026-10-30. Historical local-only checkpoints below are superseded by the final release receipt. No broadcast sending enabled.

## Record recovery note
On 2026-10-30 a documentation append used the Windows default text encoding and failed while writing a Unicode arrow. The acceptance file was truncated. This record is reconstructed from measured command outputs, browser evidence and the current phase handoff; it does not claim to reproduce the prior wording verbatim. Application data and code were unaffected. Historical accounting not present below is unavailable.

## Scope and invariants
- JW customer/order/enquiry/newsletter/import sources form one deduplicated audience.
- Purchases do not grant marketing consent. Unsubscribed/suppressed/conflicting identities remain excluded.
- Contacts, Segments, Import and Export preserve existing operational behavior and English UI.
- Campaign drafts may reference segment ID/version; no audience broadcast was enabled or sent.
- Preserve existing customer records, payment/quotation paths, accounts and Hub callback.

## Prior local functional evidence (from phase handoff)
- 40 PHP tests / 227 assertions passed, 15.10 seconds; surrounding Email/draft/permissions included.
- 4 frontend MarketingAccess tests passed.
- Normal local browser: sync 60 sources into 18 contacts; one valid synthetic import made 19 contacts. Email eligible 0; contacts with paid orders 2.
- Synthetic CSV import preview, explicit confirmation, completion and private export/download verified.
- Local segment: Local Audience acceptance - Customers; 9 matched, 0 eligible per channel.
- Campaign draft 01a18867-87aa-73d7-a7ce-974a74b6d725.
- Import task 8f883d03-c1b0-4bcc-986c-b3909abcca3d; export a4c5a152-65b9-407e-93bd-623fb9b9157b.
- Artifacts: output/audience-crm-source-manifest.json, output/audience-local-scope-manifest.json (hashes predate later UI corrections), output/audience-browser-acceptance.csv.
- Unrelated historical stock fixture issue count 2 was outside scope; not rerun or changed.

## 2026-10-30 whole-Audience visual correction
Root cause: CRM source styles were present but generic classes were used for tables, identities, labels and forms. Create/edit had also been reduced to a modal.
- Contacts: CRM identity/avatar, uppercase small header, rounded source/subscription labels, source stat icons, consistent filters and dates.
- Segments, Import and Export: source section/table/form/upload patterns reused. No unsupported XLSX upload promised; CSV remains 2 MB / 5,000 rows.
- Browser 1536x900: contact table/container both 1112.8px; header font 11.2px. Segment section headings 16px.
- Audience/Campaign expanded chevrons: font-size, width and line-height all 12px.
- Evidence: output/audience-contacts-style.png, output/audience-import-style.png, output/audience-export-style.png, output/audience-segment-dialog-style.png (superseded by full-page editor), output/audience-navigation-style.png.
- Route permission check: 1 test / 5 assertions, 6.89s.

## Segment list filter and actions
- Generic Bootstrap menu inside scroll container replaced with CRM fixed-position popover, icons, Escape/outside-click/scroll close.
- Collapsible filters: search, active/draft/archived state, 25/50/100 per page, reset. Backend validates inputs and retains merchant scope.
- Initial click handler now binds before awaiting list loading.
- 21 tests / 123 assertions passed, 17.99s.
- Browser unmatched search, reset and clipping verified. Evidence: output/audience-segment-filter-actions.png.

## Full-page segment presets and builder
- Source reference: crm/segment_create.php and crm/app_includes/services/SegmentRuleService.php from supplied CRM ZIP.
- Full-page Create: preset selection then editable details/conditions and preview sidebar. Edit opens its own authenticated route.
- Twelve source preset cards retained. Birthday Month is explicitly unavailable because JW has no birthday data; no fictitious data or unsupported matching is claimed.
- Remaining presets use recorded channel subscriptions, first/last successful purchase evidence, relative dates, currency-specific net paid amounts, repeat/never purchased and catalog categories.
- Nullable description and active/draft status migration applied locally; original segment IDs/rules/versions retained. Archived remains restorable; drafts cannot provide campaign recipients.
- Preview shows matching samples/count and per-channel eligibility; never sends messages.
- Local browser: Repeat Buyers preset, preview (1 matched, 0 consent-eligible), save, list, independent edit. Name, description and condition retained.
- Local acceptance segment be92978a-f41f-4495-b247-86b363b6526b.
- Evidence: output/audience-presets-page.png, output/audience-full-page-edit.png.
- 24 tests / 184 assertions passed, 20.21s before added category case.
- Category case initially failed on fixture missing categories.slug before the query ran (unexpected issue count 1). Corrected fixture to actual Category/Product/Variant fields; no assumed factories.
- Corrected suite: 25 tests / 187 assertions passed, 19.79s. JS/PHP syntax and scoped diff checks passed.

## Remaining limits
- Local source only; GitHub/Hostinger unchanged. No paid API, broadcast, commit, push or deployment.
- Birthday data collection/filtering is not implemented; original CRM fields without JW data are not claimed operational.
- Elapsed time and token usage unavailable except measured test durations above.

- After adding source Archived status option, focused metadata/archive regression re-run; result recorded in final tool output. Draft/archived recipient guards retained. Narrow viewport DOM measurement: viewport 390px, document 385px; no horizontal overflow.

## 2026-10-30 birthday data connection
- User authorized nullable customers.date_of_birth and Birthday Month connection, with My Account entry deferred. Existing dates remain NULL.
- Audience stores only derived birthday_month, not full birth date. Registered customer synchronization updates or clears the projection; no consent is granted.
- Enabled Birthday Month preset with calendar month selector; validates 1-12 and rejects future customer dates.
- Run: php artisan test --compact --filter=MarketingAudienceTest; purpose: date projection, update/clear, eligibility and preset regression. Result pending.

- Birthday regression result: 27 tests passed / 208 assertions, 19.42s. Migration applied locally. JS syntax and scoped diff checks passed. Normal authenticated page shows enabled Birthday Month with January-December selector; empty source birthdays produce no matches. No original customer dates filled or marketing consent changed. My Account UI remains deferred.

## 2026-10-30 production Audience worker readiness
- User-reported loading at 0 rows. Read-only DB at 04:15:39 UTC: sync ff77b7bd-b54a-4531-828e-2993542525b7 queued since 04:10:59, queue job attempts 0/unreserved, no failed Audience jobs; 21 Ecommerce customers, 0 Audience contacts.
- Executed existing marketing:process-audience once, reusing original task. Completed 50 source rows, 27 deduplicated contacts, 2 with paid orders; no messages or providers called.
- Hostinger hPanel showed direct Posts cron but no dedicated Audience worker. Added one isolated minute cron with flock; existing cron entries retained:
  /usr/bin/flock -n /home/u762348670/apps/jwecommerce/storage/framework/marketing-audience-worker.lock /usr/bin/php /home/u762348670/apps/jwecommerce/artisan marketing:process-audience --no-interaction
- Deployment readiness previously checked migration/assets but did not verify worker consumption; that missing production gate caused the queued state.
- Created one new normal-page sync after original completion to verify natural cron consumption. Result pending. Source code SHA remains 9019bee0532ddea753d12ace0ae121335912a12a; this is hosting configuration plus local documentation, not a code patch.

## Page-driven sync integration (local, 2026-10-30)
- Approved UI preview: public/audience-sync-preview. Sync only becomes page driven; import/export and unrelated workers remain unchanged.
- Intentional TDD red: run tests matching sync_is_page_driven or worker_cannot_advance before implementation, expected missing continue endpoint/checkpoint and existing worker dispatch.

- Intentional red confirmed 2 missing page-driven guarantees. Implemented continue endpoint with checkpoint hash checked under task lock, manage/store permission, and sync worker exclusion. Focused green: 2 tests / 34 assertions, 4.02s. Next runs: full Audience PHP regression; node tests/javascript/audience-page-sync.test.cjs covering ready/no auto-start, checkpoint resume, network interruption and visibility pause.

- Full PHP regression passed 29 tests / 242 assertions, 14.04s. Frontend fake-response tests passed 4/4. Initial browser gate found missing new script include (integration issue count 1): asset version query prevented string replacement from matching. Added explicit script include and route-view asset assertion; no task was created during that first click. Next: focused view check plus normal browser sync.

- Focused route-view check after asset correction passed: 1 test / 7 assertions, 3.85s. Scoped diff check passed. Whole-worktree diff check reports pre-existing trailing blank lines in unrelated TikTok/WhatsApp acceptance files; those files were not changed.
- Normal localhost browser: clicked Sync Audience, immediately left for Segments; task 05b906a3-eb77-4067-88e6-9daa20b09d08 remained queued with processed=0 from 04:45:49 UTC until returning to Contacts. Returning automatically continued it; saved contacts stayed visible during processing (60 source records checked, 85% stage estimate). Reload then showed the same task completed, with 31 deduplicated contacts and 2 contacts with paid orders. No new sync task was created by return/reload.
- Screenshot: output/audience-page-sync-complete.png. The record count is source rows checked; percentage is stage progress, not a claimed total-contact percentage.
- Local acceptance complete for approved page-driven Sync scope. No provider calls, messages, commits, pushes or deployment. Production remains on the prior release with its existing Audience cron until a separately approved synchronization; this local change does not alter production configuration.
- Final frontend fake response evidence: 4/4 passed (explicit start, same-checkpoint continuation, interruption/manual continue, hidden-page pause). Backend suite: 29/29 passed. Browser asset integration issue count 1 resolved; no blind reruns. Total elapsed time/token usage unavailable.

## Approved page-sync release gate (2026-10-30)
- User approved synchronization after local acceptance. Baseline local/release-worktree/GitHub/Hostinger HEAD 9019bee0532ddea753d12ace0ae121335912a12a. Hostinger origin remains SSH Deploy Key URL; tracked source clean, unrelated untracked configuration backups/assets retained.
- Explicit 13-file allowlist includes Sync service/controller/route/middleware, approved UI assets/view, tests and three Audience documents. No migrations, .env, provider credentials, other modules or generated Admin build included.
- Release verification command: isolated PHP Audience regression plus node frontend fake-response tests; purpose is verify exact allowlisted source independently of dirty canonical workspace. Result pending.
- Isolated release harness issue count 1: PHP suite lacked APP_KEY (26 errors share MissingAppKeyException; not application regressions). Frontend 4/4 passed. Supply an ephemeral test-only key through process environment, never copied from production or committed, then rerun the same release gate once.
- Corrected isolated release harness passed 29 tests / 244 assertions, 10.083s; ephemeral APP_KEY was process-only. Frontend 4/4 passed. No application fix needed for harness issue.

## Page-driven Sync release completed (2026-10-30)
- Approved 14-file code release c2eaad934aab53e3b720c24bed75619140a8253c synchronized. Canonical local content hashes verified before non-destructive fast-forward; unrelated dirty source retained. Isolated release 29 tests / 244 assertions passed; frontend 4/4 passed.
- Normal production Contacts verified start/leave/return/refresh/completion of same task 1a033cce-17ed-44a3-82aa-f4ee331be267. 50 records checked; contacts 27 -> 27; paid-order contacts 2; no sync queue job. Worker no longer advances Sync tasks.
- Production JS/CSS return HTTPS 200 and match committed Git blobs; normal UI shows Your audience is up to date. Existing accounts, Hub callbacks and unrelated schedules preserved. Dedicated Audience Cron retained only because import/export still need it; no new per-domain task added.
- Remaining gates consolidated in docs/acceptance/2026-10-30-marketing-audience-closeout.md: Import/Export production and portable execution, My Account birthday collection, scale/employee UI tests, Email bulk and unsubscribe, WhatsApp price/approval/receipts, SMS transport. No overall Marketing completion claim.

## Page-driven Import/Export local phase 2026-10-30
- Approved scope: same as Sync; current baseline 9b6a63eb. No release. Intentional initial TDD red: two new transfer tests expect no queue dispatch, authorized checkpoint continuation, preview stop and expiry. Run only new tests before application changes.
- Intentional red confirmed queue dependency and missing expiry state (2 tests / 5 assertions, 5.04s). Implemented all-kind page guard, removed dispatch from start/confirm and automatic schedule, extended existing continuation permissions/checkpoints/expiry. Next focused run validates those contracts.
- Focused backend green: 2 tests / 32 assertions, 4.06s. Intentional frontend red: new transfer runner API not implemented yet; fake cases require preview stop, resume, interruption and visibility lock.
- Frontend intentional red confirmed runner absent. Implemented transfer controller and existing-form bindings, same inline panel, explicit preview confirmation, form locks, visibility pause and network Continue. Run JS fake cases/syntax before full PHP regression and browser acceptance.
- Full backend 31 tests / 276 assertions passed (14.31s); JS 9/9 passed, syntax/scoped whitespace passed. Added >100-row import/export test and kept create forms locked during network interruption, so Continue cannot accidentally become a second submission. Confirm request failures read existing task state instead of blindly resubmitting. Next focused multi-batch test plus frontend rerun; normal synthetic CSV has 2 valid / 1 invalid rows.
- Multi-batch regression passed 1 / 14 assertions (4.35s); frontend 9/9. Normal localhost import 71baf279-6bab-4b7f-bf12-fe897408a1ae created, left at queued/0 rows; returning automatically reached preview 2 valid/1 invalid, while original 31 contacts remained unchanged before confirmation. Browser noted recent-history rows retained initial queued label; refresh history on terminal/preview response. No change to import decisions.
- Normal localhost: imported task 71baf279-6bab-4b7f-bf12-fe897408a1ae paused at queued/0 on leaving, returned to 2 valid/1 invalid preview. Reload did not apply anything. Explicit Confirm Import then leave/return completed same task: processed=2, valid=2, invalid=1; total contacts 31 -> 33, no subscription inferred. Existing original contacts retained.
- Normal localhost export 25dc7ab2-afee-495d-b927-792884371f37 used Imported contacts filter only (synthetic local records). Left at queued/0, returned/refreshed, completed same task with 3 data rows. Normal Download CSV saved a real file; CSV inspected as 3 data rows / 5 columns. No provider/production request. Console warnings/errors empty. Screenshots output/audience-page-import-complete.png and output/audience-page-export-complete.png.
- Final gate: run complete 32-test Audience suite after generic continuation, expiry, >100-row case, history refresh and confirmation-read recovery changes; rerun both frontend fake suites and scoped syntax/whitespace checks. No unexplained failure so far; initial reds intentional.
- Final full backend suite 32 tests / 290 assertions passed, 17.06s. Frontend 9/9 and JS/scoped diff checks passed. Added one final readonly UI assertion: queued transfers show an access notice instead of suggesting progress when current user cannot continue. No backend change after full pass; focused frontend suite rerun follows.
- Final frontend result 10/10 passed (including read-only access notice). Local phase ready for user review; no commits/push/deploy or production Cron changes. Remove only the dedicated Audience processing cron after a separately approved release; unrelated workers and daily file-retention maintenance remain unchanged. Total elapsed/token usage unavailable.
