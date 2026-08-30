# Task 4 report — Hostinger minute cron entry point

## Outcome

Implemented one bounded Artisan entry point for Hostinger shared hosting:

```sh
cd /home/u762348670/apps/jwecommerce && php artisan easyparcel:cron --no-interaction
```

`easyparcel:cron` acquires the atomic `easyparcel:cron` cache lock for 55 seconds,
runs due Laravel schedules, then drains the `default` database queue with
`--stop-when-empty --tries=1 --timeout=45 --max-time=50`. The lock is released
in `finally`, and an overlapping invocation exits successfully without dispatching
or processing work.

The EasyParcel reconciliation sweep now runs every minute. AWB tracking remains
on its existing 15-minute cadence.

## TDD evidence

### RED

Command:

```powershell
C:\xampp\php\php.exe artisan test tests/Feature/RunEasyParcelCronTest.php
```

Observed before production changes:

- 3 failed, 6 assertions.
- Both command behavior tests failed with `The command "easyparcel:cron" does not exist.`
- The schedule test expected `* * * * *` but observed the old `*/5 * * * *` reconciliation expression.

### GREEN

Command:

```powershell
C:\xampp\php\php.exe artisan test tests/Feature/RunEasyParcelCronTest.php tests/Feature/EasyParcelReconciliationRecoveryTest.php
```

Observed after the minimal implementation:

- 15 passed, 98 assertions.
- The real nested `schedule:run` and `queue:work` commands executed inside the
  feature test; no injectable or mocked command runner was required.
- The controlled scheduled database job produced its real cache side effect and
  the database queue was empty after the command.
- An independently held atomic lock prevented all scheduled/queued side effects
  and returned exit code 0.

## Additional verification

- `php artisan schedule:list` reports reconciliation every minute and tracking every 15 minutes.
- `vendor/bin/pint --dirty`: passed.
- `git diff --check`: passed.
- Existing user-owned unstaged `AGENTS.md` was not modified or staged.

## Concerns

- Hostinger still needs the documented command configured as a once-per-minute
  cron entry after this exact commit is deployed.
- Correct cross-process overlap protection requires the production cache store to
  support Laravel atomic locks; the current application cache configuration must
  remain backed by a lock-capable shared store on Hostinger.
