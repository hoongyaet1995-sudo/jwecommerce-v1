# Connection time metadata (local)

- Root cause: connection API exposed SQL datetimes without a timezone; browsers interpreted them in their local timezone. Card also rounded remaining time to days, masking short token lifetimes.
- Connections now serialize expiry and last successful sync as UTC ISO timestamps, interpreting database values in the configured application timezone.
- Last Sync comes from the maximum completed successful catalog/order task per store, not a failed/queued task or browser click. No successful task returns null. Query uses grouped aggregates rather than per-store reads.
- Operational card renders the explicit expiry in the browser locale; demo retains its original day display. Existing DOM/classes remain unchanged.
- Authorization feature suite 17 tests / 96 assertions passed; Auth page 2 tests passed; TypeScript passed. No remote provider calls or production writes made.
- Local changes pending integration into the next coherent release; production remains bba88ae7.
