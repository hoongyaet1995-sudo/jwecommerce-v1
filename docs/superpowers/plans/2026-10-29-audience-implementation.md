# Audience implementation

Approved 2026-10-29. Execute inline in canonical root, no release.
1. Add fake feature tests for permissions, sync identity conflicts, consent precedence, segments and private CSV jobs. Declare initial red before run.
2. Add audience projection/source/subscription/segment/task/import-row schema. Projection links to existing customer; never rewrites commerce records. Dedicated queue processes bounded chunks.
3. Add AudienceDirectory identity and metrics projection, AudienceSegments validated SQL rule compiler, AudienceTransfers CSV parsing/private output, AudienceTasks checkpoint processor.
4. Add authorized controller/routes, separate employee grants and source-derived English CRM views/assets. Add navigation on existing Studio shells.
5. Verify focused suite and route/schema/JS/PHP checks; migrate locally; browser Contacts/Segments/Import/Export with synthetic data. Record evidence in acceptance file. Preserve unrelated stock fixture issue count2. No sending/push/deploy.

Rulings: import max2MB/5000 rows CSV UTF-8, export max50000 rows; jobs100 rows per chunk; private downloads expire24h and files prune after7days. Imported consent stays unknown; no unsupported resubscribe UI. Explicit unsubscribe/global suppression management allowed; Newsletter live published producer is opt-in evidence. Segments restricted to one AND/OR group of max10 validated rules in v1. Customer master unchanged, audience projection includes source links; ambiguous overlaps are excluded until resolved. Baseline e13cd232; dirty workspace preserved.

Ruling: enable segment choice and channel-eligible count preview in Campaign drafts, with server-scoped segment/version reference. This is preparation only; bulk dispatch stays disabled. Existing single-recipient test email remains explicit and independent of the selected segment.

Status2026-10-30:steps1–5 locally implemented and verified; user review pending. No git/release action. Evidence and operational worker prerequisites recorded in acceptance.

## 2026-10-30 source workflow correction
- Full-page preset/create/edit replaces the reduced modal. Source CRM grid, details/conditions and preview sidebar restored.
- Preset registry and supported paid-evidence/relative-day/category conditions added. Birthday remains unavailable pending real customer birthday data.
- Description/status migration applied locally; original records retained. Local browser preset, preview, save and edit verified; acceptance evidence is in the Audience acceptance record.
- No commit, push, deployment or delivery.

## Birthday storage connection (2026-10-30)
- Added nullable customers.date_of_birth and indexed Audience birthday_month projection. Enabled Birthday Month preset. Sync handles month update/clear without consent changes.
- 27 tests / 208 assertions passed. My Account collection UI deferred by user; no birth dates invented or backfilled. Local only.
