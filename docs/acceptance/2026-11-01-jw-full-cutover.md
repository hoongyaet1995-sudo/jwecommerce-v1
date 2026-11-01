# JW whole-site replacement — 1 November 2026

User explicitly requires replacing the official website and Admin, retaining no legacy handlers, preparing Privacy/Terms and reapplying for TikTok. This supersedes earlier callback compatibility restrictions. Ecom and Social Hub are separate and remain unchanged.

Use approved SaaS template Page54, content/contact/terms via existing CMS. No new backend UI. Independent corporate DB/key/session/owner retained. Only public assets and a front controller are exposed; old public root is moved to a private offline backup. No legacy callback compatibility. Domain aliases /privacy and /terms redirect to canonical CMS pages. No paid API.

Local tests: 51 TS tests passed; 38 PHP tests /197 assertions passed. Local content migration validated home54, Privacy53, Terms55, JW contact56. Existing local contact-us Page50 accidentally matched the generic slug during first content migration; restored immediately from recorded revision131, verified original title, and JW contact moved to unique contact-jw-studio. Importer now checks ownership before updates. Local Ecom homepage/published content unchanged.

Next gates: isolated source build; corporate content/brand/menu setup; full public-root replacement; homepage, CMS/Admin, legal, assets and mobile HTTPS proof; TikTok actual resubmission status. Keep final issue count/history in this file. Time/token usage unavailable.

## Final cutover and acceptance

- Source/build/config shipped through GitHub main to independent corporate application. Public root replaced; old application is offline only. No legacy callback handlers run.
- Corporate home3, Privacy2, Terms4 and Contact5 published. Independent DB/session/key; authenticated Admin Pages lists five pages, four published; Inspector opens normally after user login.
- Public-root mapping supports future public disk uploads and SSR. Clean build passed (11.95s); focused TS 51 tests and PHP 38 tests/197 assertions passed.
- Independent subdomain mounts were initially absent after root swap (one observed Social Hub404). Restored exact existing ecom/social/marketplace/qiaojing mount links immediately; ecom and Social Hub subsequently200. Cutover script now preserves those links. Do not rerun completed cutover.
- HTTPS bare/www homepage, Admin login, Privacy, Terms and Contact verified. Desktop and390px mobile checked without horizontal overflow. Evidence under output/jw-corporate-migration/acceptance: jw-home-live.png, jw-home-live-mobile.png, tiktok-resubmitted.png.
- TikTok developer profile actually resubmitted; authoritative UI says Your profile is currently under review. This is not approval and does not enable messaging yet.
- Authenticated CMS listing and Inspector verified. File chooser accepted preview PNG for unpublished draft, but resulting asset URL was not observed; upload end-to-end remains unverified and is not counted as passed. Live homepage untouched by this upload attempt.
- Browser CDP initially timed out once; fresh current tab state succeeded. No blind repeated mutations. Elapsed total/token usage unavailable.
