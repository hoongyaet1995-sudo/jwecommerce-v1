# JW whole-site replacement — 1 November 2026

User explicitly requires replacing the official website and Admin, retaining no legacy handlers, preparing Privacy/Terms and reapplying for TikTok. This supersedes earlier callback compatibility restrictions. Ecom and Social Hub are separate and remain unchanged.

Use approved SaaS template Page54, content/contact/terms via existing CMS. No new backend UI. Independent corporate DB/key/session/owner retained. Only public assets and a front controller are exposed; old public root is moved to a private offline backup. No legacy callback compatibility. Domain aliases /privacy and /terms redirect to canonical CMS pages. No paid API.

Local tests: 51 TS tests passed; 38 PHP tests /197 assertions passed. Local content migration validated home54, Privacy53, Terms55, JW contact56. Existing local contact-us Page50 accidentally matched the generic slug during first content migration; restored immediately from recorded revision131, verified original title, and JW contact moved to unique contact-jw-studio. Importer now checks ownership before updates. Local Ecom homepage/published content unchanged.

Next gates: isolated source build; corporate content/brand/menu setup; full public-root replacement; homepage, CMS/Admin, legal, assets and mobile HTTPS proof; TikTok actual resubmission status. Keep final issue count/history in this file. Time/token usage unavailable.
