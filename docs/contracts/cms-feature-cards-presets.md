# Feature Cards presets

Superseded locally by [standalone Values List](cms-values-list.md). The following describes the abandoned trial, retained only for existing saved-data read compatibility. New authoring uses `values_list`; there is no Feature Cards preset selector.

`feature_cards` owns `featureCardsPreset` (`default`, `values-list`) and optional boolean `valuesShowHeading`. Missing preset retains the original icon-card renderer. Values List uses the same renderer in Builder and saved page preview, without page or template conditions.

Values List displays subtitle, ordered numbered rows, item title and description. Numbers derive from current order, starting at 01. Stable item IDs survive reorder, edits and save. Its English Inspector supports Add Value, Delete, Up and Down. Icon, badge and other existing item fields remain stored for switching back to Feature Cards.

The section heading and introduction are hidden by default, matching the approved About reference. Show section heading reveals both and their controls; hidden content remains stored. The preset owns left alignment, row geometry and mobile stacking. Existing section spacing, background and explicit custom colors still apply. No database migration or AI Planner eligibility change is included.

Acceptance covers rendering, preset preservation, editor interactions, PHP family/enum/boolean validation, database-backed draft round trips, and localhost saved preview at desktop/mobile widths. Original card markup remains unchanged.
