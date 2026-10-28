# Channels platform navigation — local integration

Baseline `2b697103698b921c3aea969947b0a97bbe488869`. User approved the Bootstrap two-level sample and removal of overview account avatars, names and counts.

Implemented locally:
- `/admin/marketing/channels`: four platform cards only; no account records, totals or OAuth form rendered in the content area.
- `/admin/marketing/channels/{facebook|instagram|tiktok|youtube}`: protected GET platform details; no provider probe.
- Facebook/Instagram: platform-filtered real account list, local search, Connect account, per-account Manage with actual status/scopes/check/disconnect. Only explicit form submission initiates OAuth. No simulated account creation in the formal UI.
- TikTok/YouTube: navigable planned-platform pages with disabled connection actions.
- Existing Hub callback/query and Meta fragment handling retained; pending picker opens the appropriate platform. Existing encrypted records and stable account IDs retained. The database schema and Hub configuration are unchanged.
- English copy and approved Bootstrap sample structures/styles retained; new CSS scoped to Channels pages.

Verification: 47 PHP tests / 444 assertions / 10.71s and 9 Node tests passed. Coverage includes multiple records, add/reconnect preservation, read-only staff access, platform routes, invalid platform 404, no automatic provider calls, hidden overview identities/counts, callback query, Meta fragment and CRM regressions. PHP/JS syntax and scoped diff checks passed.

Normal local browser acceptance: authenticated Joshua profile inspected all four formal platform routes and Back to channels. Overview contains four cards without account names/avatars/counts. Facebook and Instagram correctly show empty states because the local Marketing connection table is empty; domain-mismatch protection keeps OAuth disabled. Search produces No matching accounts without provider requests. TikTok and YouTube show Coming soon with disabled connection actions. Screenshot capture succeeded in this session. A shared label rule caused the search control to display as a block; a scoped CSS correction now computes display:flex with no horizontal overflow at the current desktop viewport. Actual multi-record rendering, filtering and per-record Manage dialog were validated with synthetic frontend fixtures (two Facebook records, one Instagram); no real records were seeded/imported. Do not treat these as live multi-account OAuth acceptance.

Not committed, pushed or deployed. No real OAuth, token import, connection mutation, paid API call or publication performed during this integration. Unrelated dirty work and the prior paused paid-pilot issue remain untouched.
