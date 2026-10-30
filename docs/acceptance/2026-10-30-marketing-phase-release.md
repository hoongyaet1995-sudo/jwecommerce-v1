# Marketing phase release

Approved 2026-10-30: Audience page-driven Import/Export, Email unsubscribe, Campaign/Template/Posts table actions. No unrelated storefront/inventory changes. No real send.

Ledger: release-isolated suite first run blocked by MissingAppKey (98 tests hit same environment cause; issue count 1). Added ephemeral testing-only APP_KEY to untracked release PHPUnit configuration. No production key copied or changed. Rerun after environment repair.

Release-isolated verification after test configuration repair: 104 tests / 680 assertions passed (16.928s); JS 10/10 passed. No DB migrations or provider calls. Prior normal localhost evidence retained in phase acceptance records.
