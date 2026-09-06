# Marketplace read-stage runtime readiness

Read-only inspection on 2026-09-06. No worker or cron was started or changed.

## Current evidence

- Hostinger `/usr/bin/php -m` lists `pcntl` and `posix`, so CLI signal-based timeout support is present.
- `pgrep -fc "[p]hp.*artisan queue:work marketplace"` returned 0 at inspection time. This is a bounded process-pattern check, not proof that no differently named process exists.
- A user-crontab search for `marketplace` returned 0 matches. This does not inspect all hosting control-panel or system-level schedules.
- Local `php artisan schedule:list` contains only the two EasyParcel scheduled jobs. No Marketplace schedule is registered in `routes/console.php`.
- `config/queue.php` declares a dedicated database connection/queue named `marketplace`, retry_after 1500 seconds and after_commit true.
- Catalog jobs declare timeout 1200 seconds; order jobs 120 seconds. Both have one try and failOnTimeout true. The configured retry interval exceeds either timeout.
- Shopee HTTP requests use connect timeout 10 seconds and overall timeout 20 seconds. Catalog pages request at most 50 items and may fetch a model list for each item; the long catalog timeout must be considered when selecting hosting cron limits.

## Release acceptance sequence (not executed)

1. Obtain phase synchronization approval and deploy the same accepted Git commit to Hostinger.
2. Verify the only intended enabled provider is Shopee Sandbox and verify the connected store identity. Enable read operations only; keep the general operations flag false.
3. Inspect pending Marketplace job identities/counts without printing serialized payloads or credentials. Do not drain unknown pre-existing jobs.
4. Enqueue the controlled catalog/order reads through protected application endpoints. Run a bounded dedicated Marketplace worker for those acceptance jobs, not the default/EasyParcel queue.
5. Verify actual persisted product/model/order identities, task counters, retries and refresh persistence. A submitted job is not a successful import.
6. Before unattended operation, configure a hosting-supported dedicated worker or bounded non-overlapping cron runner, validate it across more than one invocation, and provide stale-job recovery/monitoring. A one-off acceptance worker does not prove ongoing scheduling.

Do not add Marketplace draining to `easyparcel:cron` or run an unrestricted worker as a shortcut. Current readiness checks intentionally do not claim a live worker or real provider acceptance.

## Remaining gaps

## Local bounded runner added (not deployed or installed)

`php artisan marketplace:cron` now provides a dedicated entry point. It exits without consuming jobs when both operation flags are disabled, acquires a shared-cache `marketplace:cron` lease for 1500 seconds, runs only the `marketplace` connection/queue with `--max-jobs=1`, `--stop-when-empty`, `--tries=1`, `--timeout=1200`, then releases the lease. It does not run `schedule:run` or enqueue catalog/order reads by itself.

Three local integration tests use the real database queue with synthetic jobs, not a mocked worker. They verify one job per invocation, a second invocation processes the next job, default/EasyParcel queues stay untouched, disabled operation consumes nothing, and an existing lease prevents processing. Tests were first observed failing because the command did not exist, then passed (14 assertions).

Host installation still requires phase approval. Use a shared persistent cache lock backend, not the test-only array cache, and a PHP CLI with `pcntl`. Hosting must allow a catalog job to run up to 1200 seconds; otherwise reduce/chunk catalog work before enabling the cron. The lease cannot protect against an external worker deliberately bypassing this entry point. Job-level page claims remain the additional duplicate guard. A successful cron process exit does not mean its provider task succeeded; inspect run status/error/counters.

The read-only phase is not yet published. Actual production catalog/order ingestion, unattended task execution, automatic scheduling/recovery and source-faithful operational UI cutover still need acceptance. Publishing, inventory writes and fulfillment remain outside this read-only release.
