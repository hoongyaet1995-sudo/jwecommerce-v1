# Central AI Automation cron — staged rollout

Status: local implementation only. Do not activate production from this document without release approval.

## Installation contract

- Social Hub client registry already exists. Add each client ID to `config/social.php` automation_clients using its canonical realpath application root, enabled=true, capabilities=[marketing.ai-posts]. No arbitrary executable or URL fields accepted. Keep registry changes under operator review; no automatic filesystem discovery.
- Each installation must have its own enabled Social Hub pairing with that exact client ID. The dedicated command requires `--client=<registered ID>` and checks it before work.
- Default `SOCIAL_AUTOMATION_ENABLED=false` in Hub and `MARKETING_AUTOMATION_CRON_ENABLED=false` in each client. All existing Automation rows and Hub client scheduling gates migrate paused. No .env values or provider secrets are transferred between installations.
- Review/apply only the new schedule migration in each client and new social dispatch migration in Hub. Preserve existing data and unknown generation records. Migrations have been applied locally, not on production.

## Proposed Hostinger entry (not installed)

One minute cron invokes `/usr/bin/php /home/u762348670/apps/jw-social-hub/artisan social:automation-tick` with a private rotating log destination. First inspect actual hPanel cron inventory; do not remove existing heartbeat, publishing, inventory or other project entries. This is one entry for this Automation subsystem, not a replacement for every application's generic scheduler.

Dispatcher takes a shared lock, launches at most two registered clients concurrently using argument arrays, and records only safe receipts. Each client advances at most one durable generation step per tick. Hub child timeout900 seconds, client/Hub lock1800 seconds. Current architecture is same-server only; measure PHP CLI/runtime/process limits before enabling. Many clients or long images can exceed capacity; tune only after measurements, never compensate by resending unknown AI requests.

## Activation order

1. Pass local fake-provider tests, template validation and desktop/mobile authenticated acceptance.
2. Release accepted exact revisions for client and Hub under their respective delivery contracts. Preserve website-specific front controllers/static-root mapping.
3. Configure operator-reviewed root/client registry. Check client owner has marketing view/edit/cost approval and a valid AI connection. Leave all Automations paused.
4. Authorize one paid canary explicitly, enable only its client/Automation and choose a future slot. Install approved central cron. Verify one unique occurrence, provider receipts, multi-platform/image Draft group and no publishing attempts.
5. Enable further Automations individually. A connected client does not automatically grant paid-generation consent.

## Behaviour and monitoring

Schedules evaluate Malaysia weekdays/time and store UTC slots. Inclusive30-minute catch-up; older slots are recorded skipped. Activation and settings changes apply prospectively. No-op Save keeps the same version so it cannot interrupt scheduled work. Changed/paused in-progress generation remains needs_attention if any existing run requires review. Unknown/provider-requesting outcomes are never resent after a lock expiry.

Client management: `/admin/marketing/automation/ai-posts`. Hub management: `/admin/automation`. Hub Run due tasks records a request consumed on the next cron tick; it does not synchronously run a paid task or force an early slot. Recent dispatches include safe counts and reason; local Recent generations opens saved Drafts.

Pause central flag to stop new dispatches; pause a client/Automation to block unstarted steps. Already sent requests may complete/charge: retain them for review rather than deleting or retrying. Restore service by checking the same occurrence/generation IDs. Do not restart unknown provider work under a new ID.

## Current evidence limitations

No real cron or paid canary has run. Authenticated browser connection timed out during this phase; visual acceptance remains outstanding. MySQL concurrent-process stress and Hostinger runtime limits remain a pre-activation gate. Generic full-repository failures are recorded in the phase ledger, not silently treated as passing.
