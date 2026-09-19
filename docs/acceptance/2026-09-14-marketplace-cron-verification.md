# Hostinger Marketplace queue verification

Verified 2026-09-14 on the existing online release f979ea1.

Hostinger hPanel (jwaistudio.online > Advanced > Cron Jobs) already contains:

```cron
* * * * * /usr/bin/php /home/u762348670/apps/jwecommerce/artisan marketplace:cron --no-interaction
```

The SSH shell has no crontab command or Supervisor. This does not indicate absence of hPanel-managed cron jobs. Do not add a duplicate worker.

RunMarketplaceCron uses the marketplace database queue connection (retry_after 1500 seconds), a 1500-second cache lock, and queue:work --stop-when-empty --max-jobs=1 --tries=1 --timeout=1200. It processes at most one job per invocation; pagination can therefore span minutes. It is a periodic worker, not a continuously resident daemon.

Independent automatic execution proof:

- Browser selected Shopee shop 227906696 (JW store 2) and requested Fetch Store Products.
- Catalog run 6 was queued at 2026-09-14 03:20:37 UTC.
- No manual queue/cron execution occurred after submitting run 6.
- Existing hPanel cron completed run 6 at 03:21:03 UTC (26 seconds later).
- Result succeeded, 1 page, 1 product, 16 variants, no error.
- Product 803935041, MUSE CASUAL COLLECTION.

Earlier run 4 was manually consumed after approximately 40 seconds; that was insufficient evidence that an automatic worker was missing. The earlier no-worker diagnosis is superseded by this verification. No cron configuration or application code was changed for this check. Other applications' cron jobs remain separate.
