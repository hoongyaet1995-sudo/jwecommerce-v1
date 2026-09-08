# Marketplace dedicated worker scheduling

## Root cause and scope

Hostinger hPanel currently lists Dashboard schedule/default-queue workers and an Ecommerce EasyParcel worker, but no Marketplace worker. SSH does not expose crontab. Submitted Marketplace read jobs therefore need a dedicated hPanel schedule.

Use the existing, released command only:

`/usr/bin/php /home/u762348670/apps/jwecommerce/artisan marketplace:cron --no-interaction`

Run every minute. It consumes at most one Marketplace job, uses a 1500-second cache lease, and exits when read/general operations are disabled. It does not enqueue new syncs or process unrelated queues. Keep all existing hPanel entries unchanged. Do not expose a public HTTP worker endpoint.

## Evidence

- Local `php artisan test --filter=RunMarketplaceCronTest`: 3 tests, 14 assertions passed.
- hPanel inspected through the authenticated browser; Marketplace entry absent before installation.
- Installed through authenticated hPanel; list shows exactly one Marketplace command at `* * * * *`, with the three prior entries preserved.
- Official OMS selected Sandbox store 1 and submitted order run 4; UI reports queued, database confirms queue job 47 (`App\\Jobs\\PullShopeeOrders`). No manual worker execution in this acceptance attempt.
- Initial observations still show the job queued and hPanel output empty. Automatic execution is NOT yet proven. Continue inspecting this same run; do not submit duplicates or replace it with a manually processed acceptance claim.

## Rollback

## Automatic execution verified

- Replaced the compound Marketplace entry with the plain absolute PHP/Artisan command above. The other three existing entries were preserved; only the reproducible old Marketplace schedule configuration was removed.
- At 03:45:01 UTC run 4 remained queued, job 47 attempts=0. At 03:45:43 UTC the same run was succeeded, pages_completed=1, orders_processed=2, error_code=null, finished_at=03:45:03 UTC, job 47 absent, cache lease released.
- No manual Marketplace worker was invoked in this attempt. This proves automatic real Sandbox order consumption for this task, not all-platform or fulfillment completion. Catalog automatic execution and later reliability/monitoring remain to verify.

## Follow-up diagnosis

- At 2026-09-07 03:40:31 UTC job 47 still has attempts=0 and reserved_at=null; run 4 queued with no error, and no Marketplace cache lease. The worker has not claimed this job.
- Existing Dashboard cron logs update, so the account scheduler is not wholly stopped. Do not alter Dashboard scheduling.
- Hostinger documentation explicitly requires shell scripts for special-character cron commands: https://www.hostinger.com/support/5646919-how-to-set-up-a-cron-job-with-special-characters-at-hostinger/ . The current compound `cd ... && ...` entry is therefore suspect, not proven valid merely because hPanel saved it.
- Next controlled correction: use a plain absolute PHP/Artisan invocation without shell operators (after checking cwd independence), or a versioned shell wrapper per vendor guidance. Continue verification against run 4 / job 47 without a second fetch or manual worker execution.

Remove only the exact Marketplace entry through hPanel if needed, preserving Dashboard and EasyParcel entries. No application schema or stock changes are part of this scheduling operation.
