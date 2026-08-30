# EasyParcel Failed Booking Recovery Design

## Goal

Close the operational gap between an EasyParcel booking that never receives an AWB and the employee recovery workflow in JW Studio. A confirmed provider failure must become an auditable terminal booking attempt, must not silently create duplicate charges, and must let an authorized employee obtain a fresh quote and create a replacement booking.

## Verified current behavior

- `Get Latest Status` is a synchronous Shipment Details request; it does not require the queue worker.
- For production shipment `ES-2608-GMU2J`, both EasyParcel Shipment Details and Shipment Listing return `shipment_status_code=7`, `Schedule In Arrangement`, with no AWB, while the EasyParcel portal displays `Failed`.
- No webhook receipt exists for that shipment.
- The public EasyParcel Open API documents quotation, submit order, details/listing, cancellation, tracking, and webhook operations. It does not document a Rebook endpoint.
- Hostinger currently has no cron entry and no persistent queue worker. Laravel declares a 5-minute reconciliation sweep and a 15-minute tracking sync, but neither runs without `schedule:run`.

## Status model

Provider status and JW recovery state are separate facts.

- `pending`: booking accepted, AWB not yet available.
- `ready_to_ship`: AWB available.
- `on_hold`: EasyParcel status code 8. This is an exception but is not a delivery failure.
- `cancelled`: EasyParcel status code 0 or a confirmed cancellation response.
- `booking_failed`: no usable AWB and an authorized employee has confirmed the provider portal failure, or a future provider response explicitly reports a booking failure.
- Existing delivery lifecycle statuses remain unchanged: `shipped`, `in_transit`, `out_for_delivery`, `delivered`, `failed_delivery`, and `returned`.

`Get Latest Status` must return the exact safe provider status code/description, the check timestamp, whether an AWB exists, and a recovery disposition. It must never translate an unchanged provider response into a misleading generic success message.

When the API still reports `Schedule In Arrangement` but the configurable AWB waiting threshold has elapsed, JW marks the row `Needs Review`; it does not automatically claim that EasyParcel failed. The employee can compare the portal and use the audited manual confirmation flow.

## Recovery workflow

1. Employee clicks `Get Latest Status`.
2. If the provider explicitly reports a recoverable terminal booking state, the recovery dialog opens automatically.
3. If the API remains pending past the AWB review threshold, the dialog explains the provider/API mismatch and offers `Confirm Provider Failure & Rebook`.
4. The confirmation endpoint locks the order, shipment, quote, and recovery record, then creates exactly one append-only recovery attempt attributed to the authenticated employee.
5. JW calls the documented EasyParcel cancel endpoint as a best-effort cleanup. The per-item result is mandatory evidence; HTTP 200 alone is not considered success.
6. The cancellation result and safe provider message are stored in the recovery audit. A provider cancellation error does not erase the employee's explicit failure confirmation, but the UI must show that cancellation was not confirmed.
7. The old shipment becomes `booking_failed` or `cancelled` and can no longer receive a replacement action twice.
8. JW opens the existing EasyParcel booking modal for the same order and fetches a new quote. Old quotes are never reused.
9. The employee selects a service and submits a new booking through the existing booking endpoint.
10. The replacement shipment uses the next order shipment sequence and is linked to the recovery attempt. The recovery record becomes complete only after that replacement shipment is durably stored.

## Idempotency and authorization

- Only staff with the existing shipping dispatch permission may refresh, confirm failure, or rebook.
- Repeating the confirmation request returns the existing recovery attempt; it must not call EasyParcel cancel twice.
- An order may have at most one unresolved recovery attempt and at most one active EasyParcel shipment.
- Terminal failed/cancelled shipments remain immutable history and no longer block a fresh quote/booking.
- An active, uncertain, ready-to-ship, or in-transit EasyParcel shipment continues to block duplicate booking.
- Every manual confirmation records the employee, time, reason, provider shipment number, cancellation outcome, and eventual replacement shipment.
- API payloads and logs never expose OAuth tokens, customer addresses, or raw provider responses.

## Admin UI

The existing Google AI Studio DOM/Tailwind language remains the visual source. No general redesign is authorized.

- The row status and toast show the exact provider result, e.g. `EasyParcel reports Schedule In Arrangement (code 7); AWB is still unavailable.`
- `Needs Review` and `Booking Failed` appear in the existing Exceptions surface.
- The recovery modal explains whether failure is provider-confirmed or employee-confirmed, shows the cancellation outcome, and presents the next action.
- `Confirm Provider Failure & Rebook` is destructive-looking, requires a short employee reason, and reuses the existing quote/service-selection modal after confirmation.
- If provider cancellation is unconfirmed, the replacement modal retains a visible warning; it does not hide the ambiguity.

## Hostinger cron contract

Add an Artisan command `easyparcel:cron` as the single Hostinger entry point. It obtains a non-overlapping lock, runs `schedule:run`, and drains the database queue with bounded runtime using `queue:work --stop-when-empty`. It exits cleanly for shared hosting and does not require a persistent worker.

Hostinger runs this command every minute:

```sh
cd /home/u762348670/apps/jwecommerce && php artisan easyparcel:cron --no-interaction
```

The reconciliation sweep changes to every minute so pending-AWB and webhook tasks are republished promptly. Tracking for shipments with an AWB remains every 15 minutes to avoid unnecessary provider traffic.

## Acceptance criteria

- A current provider status of code 7 produces truthful diagnostic UI rather than a generic `latest` toast.
- A stale no-AWB shipment becomes `Needs Review` without being falsely labelled failed.
- Manual failure confirmation creates one auditable recovery attempt and one best-effort cancellation request.
- Repeated confirmation is idempotent.
- Rebooking always obtains a fresh quote and creates a new shipment sequence linked to the recovery attempt.
- Active or uncertain shipments remain protected from duplicate booking.
- Automatic provider terminal status opens the same recovery dialog.
- `easyparcel:cron` is safe under overlapping minute invocations and processes scheduled database-queue work without a permanent worker.
- Localhost tests, Admin build, authenticated browser flow, GitHub `main`, and Hostinger deployment all refer to the same accepted commit before release completion.
