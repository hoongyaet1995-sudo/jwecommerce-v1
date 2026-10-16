# Wedding Filter Drawer concept v1

2026-10-16. Visual sample only. Existing shared CatalogFilterService and GET names remain authoritative. Single category, not multi-category. Count/scope/search/sort preserved, resetting filters resets lazy-load batches.

Sample uses quote-only catalog with no public prices: show Category with All/categories/counts using actual Category records. Category inside drawer is a filter, not reinstated PLP in-page navigation. Forced category routes must retain scope and omit category changes. No availability/date/location/budget features are proposed.

Conditional presentation gap to implement: price bounds/inputs only when useful publicly priced items exist, never leak hidden quote prices through bounds; in-stock toggle not shown for quote-only service lists and never claims availability. Real color/material/rating facets stay data-conditional, not shown for this sample. If category is locked and no applicable facets remain, hide Filter trigger rather than an empty drawer.

Style: ivory panel, olive main radio/selection/buttons, serif heading and Inter labels; reset and apply share equal height. Proposed staged radio changes submit shared GET only on Show results; reset maintains actual route scope. No controls are implemented by this raster.
