# Shell Logo inheritance — 14 November 2026

User explicitly approved local verification followed by GitHub main / www / ecom synchronization.

Root cause: shell-brand treated an independent brandName as an instruction to suppress ShopBrand::configuredLogo and overlay variants. New priority retains independent brandLogo, otherwise inherits Shop Settings logo (and existing tone variants), otherwise shows the display/business name. Display name remains the image alt text. No stored settings, page documents, DOM wrappers, classes or CSS changed. Two Blade expressions changed; no frontend build required.

Canonical-first development and SQLite-memory tests in C:/xampp/htdocs/jwstudio-ecommerce. Expected initial red2 failures/6 pass, then8 tests/70 assertions pass. Normal/transparent/variant/preview regression14 tests/90 assertions pass3.80s. New regression tests preserve explicit override even with a display name and configured tone variants, clear override to inherit, remove images to text fallback, and preserve company profile. Browser localhost inherits the actual branding PNG (image complete, naturalWidth1024, height40).

Source replicated exactly into existing isolated release checkout at5092f4cb, preserving unrelated canonical dirty work. Production targets require exact SHA and live homepage/logo HTTP200 checks, plus browser image-load confirmation. No DB changes or asset publication. Final delivery evidence stays in canonical release-20261114/logo directory. Aggregate elapsed/token usage unavailable.
