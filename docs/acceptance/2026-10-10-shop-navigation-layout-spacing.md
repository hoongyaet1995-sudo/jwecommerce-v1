# Shop Navigation layout and spacing — 2026-10-10

Local development only. Existing dirty worktree preserved; no publish, push or deployment.
Root cause: Inspector own-property gating hid optional textAlignment and paddingY; image-card heading wrapper did not span the section when centered.
Fix: expose existing shared controls for shop_navigation, correct default-left selection, center/right heading across full width, preserve left flex layout. Collection banners absent padding defaults to Normal; explicit Compact remains flush.
Verification: initial test invocation passed 7 existing tests, but intended edits had incorrect relative paths (one shell path issue, corrected before next run). Then added renderer regression test; 8 tests passed with npx vitest run src/components/pages/ShopNavigation.test.tsx. npm run build passed in 13.38 seconds. Scoped diff check passed. No unexpected test failures. Historical accounting incomplete; measured token usage unavailable.
Browser: section 5 Home & Deco — Hero: enable section heading, Center, Spacious. Saved via Exit to Pages and reopened: computed alignment center and 80px top padding. Actual customer draft preview /admin/pages/54/preview confirms center and 80px padding. Existing custom gradient preserved. Published version not changed.
Evidence: output/shop-navigation-spacing/inspector.jpg.
