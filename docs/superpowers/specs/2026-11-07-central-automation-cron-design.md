# Central Automation Cron Design

Approved direction: conversation on 7 November 2026 (Asia/Kuala_Lumpur). This document records the proposal, including the approved 30-minute catch-up window. No scheduler is enabled by this document.

## Goal and boundary

One Hostinger cron entry wakes Social Hub every minute. Hub dispatches only enabled registered clients with the explicit `marketing.ai-posts` capability. Each client owns its schedule evaluation, provider credentials, source selection, generation and Drafts. No automatic publication or migration of existing generic schedulers into this mechanism.

Existing Automation days/time use Asia/Kuala_Lumpur. Existing Automations remain paused until explicitly enabled. Social pairing alone is not consent to automatic paid generation.

## Approaches

Selected first implementation: same-host fixed Artisan runner in an administrator-maintained registry. Registry stores approved canonical application roots, not user-supplied script paths. No filesystem discovery of executable cron files. Hub invokes fixed commands with argument arrays, never a shell assembled from client fields.

Future alternative: signed HTTPS wake-ups using SocialProtocol and local durable workers. Existing Hub signatures, nonces, domain verification and pause controls are reusable; a wake-up HTTP response is not proof of completed generation. Remote clients need independently available workers. This transport is not part of phase one.

## Schedule and durable execution

Client reserves occurrences with a database unique key `(automation_id, scheduled_at_utc)`, under the installation's own database/identity. Evaluate due occurrences where `scheduled_at <= now <= scheduled_at + 30 minutes`; after that record skipped occurrences without AI calls. Store UTC occurrence plus original local timezone/date for audit. Enabling or editing a schedule takes effect prospectively; it never creates earlier occurrences. Pause skips unstarted paid steps; a provider request already sent is reconciled rather than discarded.

Settings snapshots and durable generation UUIDs bind each occurrence to exactly one generation group. Manual generations remain separate and do not consume scheduled occurrences. Repeated Hub ticks must not create duplicate runs. Never replace existing UUIDs after ambiguous provider outcomes.

Hub tick only dispatches bounded per-client processes and records receipts. Clients run a dedicated AI-generation queue/runner, not the application's general schedule or publishing queue. Use process leases and database compare-and-set before every paid step. Initial proposal: global two active client runners, one active AI generation per client, configurable after measured acceptance. Provider call timeouts and worker leases must be aligned; lock expiry alone never authorizes resending a requesting step.

States: queued, running, completed, needs_attention, skipped, cancelled. Record started/finished times, occurrence, setting version, generation ID, machine-safe error and reason. Provider unknown outcomes become needs_attention with no automatic retry. Confirmed local-only work may be recovered against the same identifiers. Completion means all intended Drafts were saved.

## Management and privacy

Hub lists client capability/enabled state, last dispatch/contact, queue counts and safe errors. Run due tasks evaluates actual due work and observes the same uniqueness key and limits. It is not force-generate. Client UI provides Active/Paused, next due time, and Recent generations. Existing history links remain available.

Hub receives identifiers/counts/states only; no catalog, full prompt, provider keys or generated media. Client execution requires a valid owner/company identity, enabled AI connection and explicit Automation activation. Separate ecom and corporate installations retain separate data and execution permissions.

## Evidence and outstanding facts

Read-only inspected deployed Social Hub fb249430: routes/console.php contains Marketplace commands, no AI Automation dispatcher. SocialDelivery/SocialAuthentication already implement signed verified client delivery and nonce replay protection. Local MarketingPostAutomations exposes prepare/step/view but currently needs page-driven execution. Hostinger shell has no crontab binary; actual panel cron inventory and a safe rollout slot must be checked before installation. Never remove an existing cron until its responsibilities are mapped.

## Acceptance and rollout

Frozen-clock tests cover weekdays, timezone, exactly 30-minute boundary, outside-window skip, pause/edit/enable transitions, duplicate ticks and concurrent reservation. Mock provider tests cover two clients, partial image completion, lost response, restart, uncertain charge and draft save recovery. Existing unknown runs must remain untouched. Verify UI desktop/mobile and zero calls while paused. Final separately authorized canary: one Automation, one scheduled occurrence, real provider receipt and complete draft group. Enable only that canary first. Production cron installation and broader activation require release approval after local acceptance.
