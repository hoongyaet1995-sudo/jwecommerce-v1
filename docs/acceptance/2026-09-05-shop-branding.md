# Shop branding local acceptance

Scope: approved Company Logo upload and Save Shop Profile workflow, Admin sidebar logo, storefront header logo, and shared favicon. Mode: OPERATIONAL_CLOSURE. Runtime: canonical XAMPP Laravel/MySQL. UI: existing ShopSettingsHub layout; only bindings, validation and saving feedback change. No deployment in this phase.

| Capability | Producer / server / persisted owner | Consumer | Evidence / status |
| --- | --- | --- | --- |
| Logo persistence | Company Logo; protected /admin/api/shop-branding; store_settings.shop_profile plus public branding PNG | AdminSidebar and storefront nav | Browser upload of existing JW JPG, successful save, Admin reload and separate Shop tab show same stored PNG; CLOSED locally |
| Favicon generation | Browser canvas normalizes accepted images; Laravel validates PNG, wraps 256px PNG in ICO; UUID path persisted | Both document heads and immediate Admin favicon update | Binary ICO header/payload test; browser same ICO href on both pages; HTTP 200 image/x-icon; CLOSED locally |
| Invalid input / save failure | Client format/5MB limit; Laravel MIME/dimension/length validation | Existing error toast; previous saved brand retained | Invalid image and guest write tests; actual browser empty optional field regression fixed and retested; CLOSED locally |
| Refresh persistence | GET shop-branding, MySQL | Admin form and sidebar | Removed shop localStorage loading/saving; reloaded Admin retained image; CLOSED locally |

Browser acceptance used the existing public JW logo image, retaining the JW brand rather than introducing a test logo. Initial profile had no saved document; empty company fields now remain empty rather than saving mock registration/address details. No payment, delivery or RMA mutations.

Verification: ShopBranding PHP tests 4 passed / 26 assertions; Admin suite 342 passed; TypeScript check and production build passed. Build retains its existing large-chunk warning. Generated ICO served successfully from public/storage.

Image URL input requires browser CORS access. If unavailable, existing toast asks the user to upload the file. Server never fetches arbitrary external URLs. PNG, JPG, SVG and WebP are rasterized in an image element/canvas; SVG source is not served as the saved logo. Original aspect ratio is preserved; favicon uses a transparent square canvas. Existing open storefront tabs obtain the latest icon on reload; the saving Admin tab updates immediately. UUID paths avoid stale icon URLs. Previous generated files are retained so cached pages do not break.

Remaining release step: explicit approval to synchronize this phase to GitHub and Hostinger. No new database migration required.
