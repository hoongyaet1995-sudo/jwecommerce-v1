# Promotion dual-site release — 2026-11-03

Approved scope: Voucher dedicated workflow; Mix & Match and Add-on administration and shared storefront pricing, all-theme presentation, cart reminders, immutable order discount snapshots and refund allocation guards. Latest Add-on reminders belong to the qualifying cart item; savings belong to the discounted item.

Isolated release build from baseline 2e39763b. 48 focused PHP tests / 696 assertions passed. Storefront and Admin builds passed; existing bundle-size warnings remain. Local browser acceptance is recorded in the Add-on phase record. No local seed data or site configuration is copied.

Release diagnostic ledger: dependency junction caused broad test discovery to load duplicate classes (1 failure, resolved by focused bootstrap/path invocation). Focused isolated suite passed. No paid APIs, publishing, payments or notifications triggered. Timing and token accounting unavailable.

Four targeted 2026_11_03 promotion migrations only. Existing .env, databases, customer records and Hub callbacks retained independently on both sites.
