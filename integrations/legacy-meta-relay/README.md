# Retained Meta ingress bridge

These files are deployment sources, not automatically loaded by Ecommerce. The old application callback remains https://www.jwaistudio.online/api/webhooks/meta-social. Do not change it to Ecommerce while other Meta capabilities use it.

Install the two PHP files at the paths noted in their headers in the old project, after approval. At the beginning of `MetaSocialWebhookController::handle`, before existing normalization, add:

```php
app(\App\Services\Social\RelayMetaMessengerToEcommerce::class)->capture($request);
```

Add to the old application's `services.facebook` configuration:

```php
'ecommerce_relay_enabled' => env('FACEBOOK_ECOMMERCE_RELAY_ENABLED', false),
'ecommerce_relay_page_ids' => array_values(array_filter(array_map('trim', explode(',', env('FACEBOOK_ECOMMERCE_RELAY_PAGE_IDS', ''))))),
```

Start disabled. Confirm shared App credentials match, the database queue jobs/failed_jobs tables exist and the worker is supervised. Use a dedicated `meta-ecommerce-relay` queue on `database`; a worker with a timeout longer than the job's 20 seconds and queue retry_after longer than worker timeout. An existing every-minute Laravel scheduler can run `queue:work database --queue=meta-ecommerce-relay --stop-when-empty --max-time=50 --timeout=25 --tries=12` under `withoutOverlapping`. Observe failed jobs without printing decrypted customer messages. Successful jobs delete their encrypted bodies; failed jobs require operator retry and bounded retention (30 days).

Only explicitly listed Page IDs trigger forwarding. Raw body and original signature remain identical; Ecommerce verifies HMAC again and independently filters Page/App/merchant/enabled status. Bodies persisted in queue jobs are encrypted. HTTP redirects forbidden. Non-2xx or network failures retry; messages and notifications are deduplicated in Ecommerce.

Activation order after deployment approval:
1. Deploy Ecommerce code and the targeted migration; configure verify token from approved secret source. Keep FACEBOOK_INBOUND_ROUTING_READY=false.
2. Deploy bridge to the existing old callback with FACEBOOK_ECOMMERCE_RELAY_ENABLED=true and Page allowlist `1045679445296291,102813752379014`. Verify actual worker delivery using an isolated signed diagnostic event, not a fake customer conversation.
3. Keep callback URL and existing feed field. Enable only `messages` at App level, v25.0. Set Ecommerce FACEBOOK_INBOUND_ROUTING_READY=true after ingress routing is verified.
4. User reviews OAuth with pages_messaging/pages_manage_metadata for each Page if not previously granted. Select one Page each time. Under that Page's Actions, choose Enable Messenger receiving. Existing Page subscribed fields are retained.
5. Real DM acceptance: JW Studio first, then Elmina. Record MID receipt metadata, lastInboundAt, Inbox delta update, Page separation and no duplicates. Until this passes, status is implemented locally / provider acceptance pending.

Local Disconnect only stops Ecommerce ingestion and clears its token. It does not unsubscribe the shared Meta App or alter the old project's account. The bridge remains necessary until a separately approved callback migration when the old application is retired.
