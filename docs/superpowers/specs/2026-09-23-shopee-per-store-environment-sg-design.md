# Shopee per-store environment and Singapore acceptance

Date: 2026-09-23. Status: design for review.

## Problem and outcome

JW Ecom currently uses one global `marketplace.shopee.environment` for OAuth, token exchange, store validation, background jobs, and subsequent API calls. The connection modal displays a read-only name and a disabled MY region control. `ShopeeClient::shopInfo` rejects every region except MY. An SG Sandbox shop cannot be connected, and changing the global environment to Production would put existing Sandbox shops at risk of using the wrong app and API host.

The desired outcome is one Ecom installation that can connect Shopee Sandbox and Production shops, including MY and SG, while keeping each shop's app identity, credentials, token rotation, reads, and Hub registration isolated. First acceptance uses the SG Sandbox shop shown by the user (shop ID 227929252), followed by a separately selected Production shop. Existing MY shops must continue operating. No real stock, listing, price, fulfillment, or order-status write is enabled by this work.

## UI and user identity

Preserve the approved Connect Store modal's structure and visual language. Remove the pre-authorization Store Display Name input: Shopee's verified `shop_name` is the canonical name. Remove the disabled region selector from the authorization form; the verified `region` is shown after callback on the store card. An optional internal alias is a separate future feature and must never replace the provider identity.

When Shopee is selected, show a compact Sandbox / Production choice using existing control styles. Default new connections to Sandbox. A reauthorization locks this choice to the target store's existing environment. Lazada and other platform flows stay unchanged. The environment choice is sent to the protected Ecom start endpoint, which returns an allowlisted official `/auth` URL. The UI must not infer environment from the login page hostname after redirect.

## Application and OAuth boundary

Use explicit immutable Shopee application context for `sandbox` and `production`: environment, partner ID, server-only partner key, OAuth host, API host, and callback URL. Do not mutate a global config array during a request or job. Each context is validated independently. The existing Hub Provider Applications rows remain a developer-facing mirror; the Ecom server retains credentials and exchanges codes.

The Ecom start endpoint validates the selected environment, configured app, callback, and reconnect target, then stores environment, partner ID, configuration fingerprint, actor, expiry, and reconnect store ID in the one-time OAuth state. If Hub routing is enabled later, the signed Hub intent carries that same selected environment. The callback consumes the state before exchanging the code and restores only the context bound to that attempt. Provider callback identity must be exactly `shop_id` xor `main_account_id`, as today. Replay, expired state, changed application, wrong environment, or wrong reconnect shop fail closed without replacing existing tokens.

Shop Account exchange and Main Account seed exchange use the selected context. Main Account batches already persist environment and application ID; their processor must use that stored context for every shop and retry, never the deployment default. Partial successes and idempotent retries remain intact. A shop from one app/environment cannot overwrite a shop attached to another. The selected environment does not grant any write capability.

## Store-scoped provider operations

`get_shop_info` validates a positive shop ID, nonempty provider name, exact returned shop identity where provided, and region `MY` or `SG` for this acceptance. It stores the provider-returned name and region; no manually selected country is trusted. Unexpected regions receive a clear unsupported-region result without consuming or corrupting another store's credentials.

Every Shopee API call following authorization resolves its application context from the addressed store's persisted environment and token `application_id`. Token refresh, product fetch, order fetch, automatic order jobs, metadata warm-up, and read-side verification cannot silently fall back to the global environment. A mismatch fails for that store only and requests reauthorization where appropriate. Existing MY Sandbox stores keep their current credentials and identity.

MYR assumptions in catalog prices, order snapshots, inventory valuation, and listing flows must be audited before SG data is presented as operational. SG reads should display provider currency/region accurately and may not be converted implicitly to MYR. If any existing consumer cannot represent SGD safely, disable that specific SG action with an explicit reason; do not fabricate MYR values. SG stock push and publishing remain off until separate write-side acceptance. The existing authorization guard and capability toggles still apply to Production stores.

## Hub registration and acceptance

After Ecom commits each new store and encrypted per-store tokens, the existing signed store receipt reports provider, environment, application ID, shop ID, verified name, region, status, and expiry to Hub. Hub's unique provider/environment/application/shop identity and single-client ownership rules remain unchanged. Registration is independent of the provider callback cutover; the current Ecom callback may remain in use for this phase. A successful store must appear in Ecom and then Hub (immediate signed report or the existing five-minute sync); an unselected old store remains unchanged.

Acceptance order: local tests and browser modal → SG Sandbox OAuth for shop 227929252 → Ecom/Hub shop identity and SG region → per-store token refresh and read-only product/order calls → Production OAuth on a separately chosen real store → Production Ecom/Hub identity and read-only calls. Before the Production redirect, reconcile the Partner ID shown in the Shopee console with the configured Production ID; current screenshots from this conversation have shown two different IDs. Never claim provider acceptance from fake HTTP tests, a persisted Hub row, or a configured secret alone.

## Failure and security behavior

- UI disables an environment without a complete app configuration and explains why.
- OAuth state expires and is single-use; callback or batch retry never exchanges a code twice.
- Tokens and app secrets stay encrypted/server-side and are never sent to Hub, browser, logs, or API responses.
- A failed SG identity check, wrong-region response, wrong environment, changed app, or unavailable provider returns a staff-readable reason and leaves existing connected stores intact.
- Provider calls have bounded timeouts, no redirects with credentials, and exact official host allowlists.
- Production OAuth selection only changes the addressed authorization attempt. It must not activate stock push, publishing, order pull scheduling, or any destructive provider write automatically.

## Test and release gate

Automated tests cover both authorize URLs, state/environment binding, callback replay, SG/MY identity, Production/Sandbox token exchange and refresh, Main Account partial batches, wrong-app reconnect refusal, store-scoped catalog/order hosts and signatures, currency display, and Hub receipt separation. Browser acceptance checks the source-faithful modal and the real Ecom/Hub store rows. Develop in `C:\xampp\htdocs\jwstudio-ecommerce` first; preserve unrelated dirty worktree files. After local acceptance and the user's release approval, push the exact accepted SHA to GitHub `main`, deploy that SHA to Hostinger, and verify live HTTPS. Hub remains its independent deployment without GitHub sync.
