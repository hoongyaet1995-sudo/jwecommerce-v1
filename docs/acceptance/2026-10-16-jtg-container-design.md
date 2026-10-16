# JTG Container theme and template

## Approved design boundary

- Homepage: `exec-c698e99c-fc1a-4c90-8cde-7b01855ea6db.png` approved.
- PLP: `exec-4dfb51e7-01ee-4234-a22f-076aa76b270d.png` approved.
- Navy/white/orange industrial styling, page-aligned copy with full-width backgrounds.
- PLP size/type chips map to Product Basic Info & Pricing Tags. No new field.
- Bottom icon/copy approved verbatim; Discuss Your Requirements opens existing Support Chat. Request a Quote remains the persisted enquiry flow.
- Theme/template implementation remains pending; approval is not a claim that the new theme is integrated.

## Authorized local catalog bootstrap

## Later user correction and approved refinements

- User clarified catalog bootstrap timing: finish all Theme/Template design and integration first, then approved production synchronization, then explicitly provision customer-facing Container products/images on production. Earlier local records are not production provisioning authorization and must not be transferred automatically.
- PDP approved hardcoded section title/introduction and Product Story support CTA. Main navy / Sub orange are defaults, custom colors must propagate; light backdrops derive from colors.
- Add a dedicated shared per-product FAQ editor under Rich Content & Specs; leave existing Custom Accordions independent.
- PLP Filter by Tag reads actual Product.tags from Basic Info & Pricing. This is a new shared filter capability, not currently implemented; scope facets to eligible catalog context, preserve filtering through pagination/reset, and do not disclose hidden products. Proposed matching: OR within selected tags, AND with other facets. Product chip/Quick View tags use the same source.
- Four icon/copy strip has no current Brand & Style content editor. Treat as explicitly disclosed proposed fixed theme presentation; never claim an existing editor. Avoid inventing a duplicate branding settings system.
- Orders summary three columns: Total Orders, Total Spent, Active Orders. Bind to authenticated customer's real orders, never demo values; define paid spend and exclude cancelled/refunded spend according actual payment/refund accounting. Enquiries/unconverted quotations are not orders.
- Voucher container linework is decorative SVG/CSS layered in the card, not photo content; cutouts/perforation must render intact. Decoration follows theme color and must not block Copy/Use Voucher or text.
- Completed tracking progress uses Main-to-Sub gradient, upcoming stages neutral, actual order events only. Mobile accessible layout without overflow.
- User explicitly requests Quotations entry in My Account navigation. Add real customer-owned quotation list, not a static shortcut to one quotation. Guest session enquiries must not be linked merely by an arbitrary supplied email; cross-account access blocked and drafts hidden. Existing quote document/accept/checkout flow reused.
- Remaining images are design candidates. Account nav inconsistency in generated images superseded by canonical list: Overview, Orders, Quotations, Addresses, Saved Items, Profile, Vouchers, Sign Out. No invented Payment Methods/Preferences screens.
- Historical design checkpoint: capabilities were pending at that time. Superseded by local implementation and evidence in 2026-10-16-jtg-implementation.md; user visual acceptance and production sync remain separate.

- User explicitly requested Category/Collection and container product creation in this client demonstration project, leaving existing catalog hiding to the user.
- Canonical HEAD at bootstrap: fbb5f362647aeee043501446442d2a072b76d364.
- Local-only guarded seeder, not referenced by DatabaseSeeder or deployment.
- Created 17 product options, 8 categories, 6 manual collections, each product with one enabled inquiry variant and size/type Tags.
- General purpose 10/20/40ft; high cube 20/40/45ft; open top, flat rack, side opening, double door, refrigerated each 20/40ft; ISO tank 20ft.
- All active for local preview, purchase_mode quote, hidden price. Zero numeric price is an unused database placeholder, not a supplied commercial price; no invented stock or inventory initialization.
- Descriptions and specifications explicitly say client demo, supply/specifications/condition/delivery subject to JTG confirmation. Photographs pending approval; none attached rather than misleading reused images.
- Existing non-JTG products/categories/collections/variants/images/PDP/membership row hashes unchanged inside transaction.
- Verification: PHP syntax PASS, actual local DB bootstrap completed without error. Evidence `output/jtg-catalog-evidence.json`.
- No theme activation, menu changes, existing catalog hiding, order/payment, GitHub push or production sync.
- Unexpected application failures 0. Elapsed and token usage unavailable.

- Browser Admin product searchJTG shows17rows+header, categorychoices visible, screenshotoutput/jtg-catalog-admin.png; tab retained. Actual20ftGPpublicPDP showsPriceonrequest+Requestaquotewithdemodisclosure and no publicnumericprice. ExistingWeddingtheme stillactive(intentionallynotchanged), so remainingWeddingcopy isnotnewLogisticsthemeacceptance. Newimagesandapprovedthemeimplementationpending.
