# Full Content capability audit — local, verified

Scope: all 35 semantic section types and 67 registered starters, including layout-specific repeatable/media/action ownership. Existing authored draft data is preserved. No publish, commit, push or deployment.

Root cause: saved optional key presence was used as Content capability detection. Empty data and preset switches hid supported controls.

## Issue ledger

- CONTENT-OPTIONAL: source-confirmed; replace saved-key gating with renderer-backed capability map. Unexpected failures: 0.
- REPEAT-ORDER: generic repeatable editors lacked ordering for most types. Add ordering using existing patterns. Unexpected failures: 0.
- CHAPTER-PHOTOS: sparse Perspective/Delivery lacks photo creation; add bounded photo controls and deletion. Unexpected failures: 0.
- SLIDER-VIDEO: videoUrl consumed as image src and lacked editor. Correct video renderer and provide existing media upload control. Unexpected failures: 0.

Historical accounting for earlier tasks remains in the Inspector dropdown audit; elapsed time and token usage unavailable.

## Runs

2026-10-10: first focused run after capability/ordering/photo/video changes. Command: npx vitest run src/components/pages/UnifiedInspector.test.tsx src/components/pages/ChapterSettings.test.tsx. Purpose: detect regressions before adding full sparse acceptance matrix. Result: 77 tests passed.

2026-10-10: expanded sparse matrix across 67 starters; added sparse action, repeatable, photo, tabs, enquiry and video assertions. Command: npx vitest run src/components/pages/UnifiedInspector.test.tsx. Result pending.

First sparse matrix: 143 passed, 1 failed. Distinct test expectation issue: Editorial Overlay intentionally edits slide headings rather than global copy; fixture expected a global heading. Corrected expectation against renderer and existing dedicated editor. Unexpected count 1, resolved. Added source-confirmed chapter navigation repeatable controls and restored Chapter Policy Content wrapper.
Next run: full CMS frontend tests after these additional edits; purpose regression coverage, plus production build. Result pending.

Full frontend run: 426 passed, four failed. Three prior-test assumptions: two expect the old 56 starters, one dispatches a select event on the new image diagram group. Updated to 67 starters and actual diagram clicks (count 1 for these newly discovered old-assumption issues). Fourth: capability regression test used absent optional text as proof no text role exists. Populate supported optional text to check actual renderer roles; count 1, investigating consumer mismatch.
Next focused run: same four failing files with these specific fixture changes, plus UnifiedInspector. Result pending.

Focused run: 152 passed, two remaining failures. Legacy palette assertion now reaches new Home & Deco palette (same old-assumption issue cumulative 2); restrict historical palette equality to original defaults and retain completeness checks for every starter. Text-role test reaches blank slide eyebrow (consumer audit cumulative 2); fill repeatable supported optional text and collect all mismatches together. Source inspection also found Logo Cloud title tagged as itemTitle: fix role binding while preserving markup/classes, and bind existing fallback logo text styles. Next focused run: the two remaining files, with these changes; no blind rerun.

Third consumer text-role run: 5 passed, 1 failed collecting two empty-list situations (Classic and Chapter Navigation). Attention threshold 3 reported in commentary. Populate an actual authored entry when repeatable capability exists; this tests editability rather than treating empty collections as unsupported. Palette assumptions resolved, image diagram switching resolved. Next focused renderer test justified by these fixture changes.

Fourth text-role run: 2 passed, one failed; fixture corrections exposed a real Classic consumer mismatch: item description uses global content style instead of itemContent. Fixed role and stable item identity, keeping DOM/classes. Count 4, Attention remains until passing. Next run after this exact renderer correction, alongside new repeated field editor coverage.

Consumer role fix: three rendering tests now pass; Attention issue resolved at cumulative 4 (history retained). Added repeated-field matrix tests for every registered repeatable starter, with typed manual delivery rows and photo rows tested separately. Export a canonical fixture/matrix for PHP validation and browser draft. Next command: npx vitest run src/components/pages/UnifiedInspector.test.tsx. Result pending.

Repeated Content matrix: 188 tests passed, including 43 repeatable starter field assertions and 67 sparse starters. Next validation: php output/cms-page-style/validate-full-capabilities.php for all 67 actual starter documents; npm run build for local assets. Results pending.

67 starter canonical validation passed; isolated Page 57 created and saved via PageCrudService/PageDraftService, readback exact match, version 2, draft only. Build passed in 13.04 seconds. Next: 43 mounted repeatable Add/Add/Delete interaction cases in UnifiedInspector (changed test harness, no app change), then final frontend/backend regression run.

43 mounted Add/Add/Delete cases passed (UnifiedInspector total 231). Extend the same cases with actual input events and item reordering to verify mutation and stable identity, then run all CMS tests. Browser: opened isolated QA Page 57 and checked first 12 Inspector selections without editing user drafts.

Mounted edit/order run: 230 passed, one failed because Story Cards editor lacks shared data-inspector-item identity marker. Existing edit controls present; add the stable DOM identity attribute. Distinct test/target identity issue count 1, source-confirmed. Also align Logo Cloud heading typography role with corrected renderer. Next regression run after these precise edits.

Source audit found additional integrity issue: Chapter preset changes deleted retained entries/photos, and photo deletion dropped manual delivery rows. Preserve authored arrays across switches; photo layouts consume only photo entries, textual layouts consume only text entries. Keep dormant owned image settings across Chapter/CTA/media/video switches; foreign ownership still rejected. New diagnostics require tests/build after these code changes. tsc remains failing across existing catalog, dashboard, marketplace and preview files; capture touched-file diagnostics separately, do not claim a clean typecheck.

Additional layout audit: Manifesto and all nine embedded Chapter Policy layouts exist in Section layout but are not separate registered starters. Add explicit sparse Content checks for those ten variants. Correct embedded Chapter Policy repeatable contract; hide dormant Manifesto quote capabilities. Delete-photo paths now prune slots without deleting other owned dormant assets. Next full CMS tests plus PHP canonical tests; touched TypeScript diagnostic capture justified by new test harness typing.

Full CMS frontend suite: 59 files, 527 tests passed. PHP: 33 passed, one old invariant failed because it rejected a dormant, owned Chapter main image slot after switching to Principles. Update that check to preserve the owned slot and explicitly reject a foreign target; count 1 for changed ownership expectation. Touched TypeScript diagnostics found four fixtures lacking required isVisible (two newly added, two prior); corrected fixture types. Full tsc still has unrelated existing errors, not a passing gate. Source check: Service Highlights never renders a section heading; suppress header controls even if showSectionHeading is absent.
Next focused frontend tests cover changed capabilities/Chapter/image ownership, PHP contract suite and final build; no need rerun unchanged unrelated modules.


## Final results and limits

All issues listed above are resolved locally; cumulative failure history is retained.

- Full frontend regression: 59 files / 527 tests passed. Final changed-area recheck: 4 files / 251 tests passed (overlapping coverage, not additive).
- PHP canonical document / page style checks: 35 tests, 196 assertions passed.
- Final production asset build passed: 15.40 seconds, 3111 modules. Existing bundle-size warning remains.
- 67 actual starter documents validated through the canonical Laravel contract; isolated draft Page 57 saved/read back exactly.
- Browser: opened Content for every one of the 67 starters. Mounted interaction tests exercise Add, Edit, Reorder and Delete for 43 repeatable starters. Additional sparse checks cover Manifesto plus nine embedded Chapter Policy layouts.
- Real browser mutation: Story Cards Add, edit title/description/image/button label/destination; database readback confirms Page 57 version 5. Customer preview reload retains title, description, image and /shop action; image loaded at natural width 1448. This is representative browser persistence evidence, not 67 manual mutation cycles.
- User Home & Deco draft was not edited by this audit. QA page remains unpublished. No commit, push or deployment.
- Full TypeScript checking remains blocked by existing errors in other modules; no clean-project TypeScript claim. Four touched fixture diagnostics were corrected; final build and relevant tests pass.
- Elapsed total time and token usage unavailable. Historical accounting is incomplete as stated above.

Evidence: `output/cms-page-style/full-content-capability-matrix.json`, `full-capability-browser-controls.json`, `full-capability-browser-save.json`, `full-capability-story-content.png`, `full-capability-preview.png`.

## Per-starter Content capability matrix

The table lists active renderer-backed copy/media/action fields and repeatable item fields. Specialized editors own product data, form fields, policy tabs, delivery rows and image slots; dormant authored fields are preserved during preset switches.

| Starter | Semantic type / layout | Section Content fields | Repeatable fields |
| --- | --- | --- | --- |
| Editorial Hero | media_text_split / editorial-hero | title, subtitle, content, mediaUrl, mediaAlt, leadText, buttonText, buttonLink, secondaryButtonText, secondaryButtonLink |  |
| Editorial Story | media_text_split / editorial-story | title, subtitle, content, mediaUrl, mediaAlt, buttonText, buttonLink |  |
| Editorial Story Cards | media_text_split / editorial-story-cards | title, subtitle, content, buttonText, buttonLink | title, description, imageUrl, buttonText, linkUrl |
| Payment Cards | payment_methods / default | title, subtitle, content, paymentNote |  |
| Payment List | payment_methods / default | title, subtitle, content, paymentNote |  |
| Promotion Highlights | promotion_highlights / default | title, subtitle, content | title, description, imageUrl, sourceId, buttonText, linkUrl |
| Collection Banners | shop_navigation / default | buttonText, buttonLink | title, description, buttonText, imageUrl, linkUrl, sourceType, sourceId |
| Shop Navigation | shop_navigation / default | title, subtitle, content, buttonText, buttonLink | title, imageUrl, linkUrl, sourceType, sourceId |
| Editorial Overlay Slider | hero_slider / default | title | title, subtitle, description, imageUrl, videoUrl, linkUrl, buttonText, secondaryButtonText, secondaryButtonLink, caption |
| Hero Billboard & Video Banner | hero_banner / default | title, subtitle, content, mediaUrl, mediaAlt, buttonText, buttonLink, secondaryButtonText, secondaryButtonLink |  |
| Full Banner Background Slider | hero_slider / default | title, subtitle, content, buttonText, buttonLink, secondaryButtonText, secondaryButtonLink | title, subtitle, badge, description, imageUrl, videoUrl, linkUrl, buttonText, secondaryButtonText, secondaryButtonLink |
| Split Hero with Floating Product | split_hero / default | title, subtitle, content, mediaUrl, mediaAlt, buttonText, buttonLink, secondaryButtonText, secondaryButtonLink, cardBadge, cardTitle, cardSubtitle, cardPrice, cardExtra |  |
| Marquee Announcement & Ticker Bar | announcement_bar / default |  | title |
| Media & Story Split | media_text_split / default | title, subtitle, content, mediaUrl, mediaAlt, buttonText, buttonLink | title, description |
| Editorial Text & Quote | rich_text / default | title, subtitle, content, quote, authorName, authorRole |  |
| Brand Milestone & Heritage Timeline | timeline_story / default | title, subtitle, content | title, description, date |
| Impact Numbers & Metric Counters | stats_counter / default |  | title, description, value |
| Product Showcase Grid | product_grid / default | title, subtitle, content, productIds |  |
| Editorial Product Grid | product_listing / default | title, subtitle, content, sourceType, sourceId, productIds, productLimit, buttonText, buttonLink |  |
| Product Listing Carousel | product_listing / default | title, subtitle, content, sourceType, sourceId, productIds, productLimit |  |
| Category & Collection Tab Showcase | collection_tabs / default | title, subtitle, content, tabs, tabCategories |  |
| Flash Deal & Product Spotlight | featured_deal / default | title, subtitle, content, mediaUrl, mediaAlt, buttonText, buttonLink, dealBadge, discountPercent, dealEndDate |  |
| Tiered Pricing & Plan Comparison | pricing_table / default | title, subtitle, content | title, description, price, features, badge, linkUrl, isHighlighted |
| Visual Lookbook Gallery | media_gallery / default | title, subtitle, content | title, description, imageUrl |
| Video Stream Player Embed | video_embed / default | title, subtitle, content, videoEmbedUrl |  |
| Interactive Before & After Slider | before_after / default | title, subtitle, content, beforeImageUrl, afterImageUrl, beforeLabel, afterLabel |  |
| Shoppable Social Grid & UGC Wall | instagram_feed / default | title, subtitle, content | title, imageUrl, handle |
| Values List | values_list / default |  | title, description |
| Value Proposition & Feature Cards | feature_cards / default | title, subtitle, content | title, description, icon, badge |
| Horizontal Trust Badges & Guarantee Row | icon_list / default |  | title, description, icon |
| Product Feature vs Competitor Matrix | comparison_matrix / default | title, subtitle, content, comparisonDimensionName, comparisonOurBrandName, comparisonCompetitorName | title, description, badge |
| FAQ & Accordion List | faq_accordion / default | title, subtitle, content | title, description |
| Enquiry Form | contact_form / default | title, subtitle, content, buttonText, buttonLink, formEmail, contactPhone, contactButtonText, subjectPlaceholder, categoryPlaceholder, messagePlaceholder, inquiryCategories |  |
| Location & Map | store_locator / default | title, subtitle, content, contactAddress, mapEmbedUrl, contactAddressIcon | title, description, icon |
| Interactive Support & Inquiries Form | contact_form / default | title, subtitle, content, formEmail, contactPhone, contactAddress, contactButtonText, formSuccessMessage, subjectPlaceholder, categoryPlaceholder, messagePlaceholder, inquiryCategories |  |
| Flagship Atelier & Store Locator | store_locator / default | title, subtitle, content | title, description, subtitle, badge, linkUrl |
| Customer Reviews & Rating Cards | testimonials_slider / default | title, subtitle, content | title, description, author, authorRole, authorAvatar, rating, badge |
| Press Mentions & Partner Brand Logos | logo_cloud / default | title, subtitle | title, description, imageUrl, linkUrl |
| Editorial Image Banner | cta_strip / default | title, subtitle, content, buttonText, buttonLink, secondaryButtonText, secondaryButtonLink, mediaUrl, mediaAlt |  |
| Call to Action & Banner | cta_strip / default | title, subtitle, content, buttonText, buttonLink, secondaryButtonText, secondaryButtonLink |  |
| Lead Capture & VIP Newsletter Strip | newsletter_signup / default | title, subtitle, content, buttonText, formSuccessMessage, newsletterCouponId |  |
| Editorial Montage | media_gallery / default | title, subtitle, content | title, imageUrl |
| Editorial Image Cards | media_gallery / default |  | title, description, imageUrl |
| Editorial Text Columns | feature_cards / default |  | title, description |
| Editorial Cover | media_text_split / editorial-cover | title, subtitle, content, mediaUrl, mediaAlt, leadText |  |
| Chapter Navigation | chapter_navigation / default |  | title, linkUrl |
| Chapter Process | chapter_block / process | title, subtitle, content | title, description, icon |
| Chapter Delivery | chapter_block / delivery | title, subtitle, content, deliverySource, deliveryNote, deliveryDestinationLabel, deliveryEstimateLabel, deliveryFeeLabel | title, deliveryEstimate, deliveryFee, imageUrl |
| Chapter Actions | chapter_block / actions | title, subtitle, content, buttonText, buttonLink, secondaryButtonText, secondaryButtonLink |  |
| Chapter Story | chapter_block / story | title, subtitle, content, mediaUrl, mediaAlt |  |
| Chapter Perspective | chapter_block / perspective | title, subtitle, content | title, imageUrl |
| Chapter Principles | chapter_block / principles | subtitle | title, description |
| Editorial CTA Band | cta_strip / default | title, content, buttonText, buttonLink |  |
| Service Highlights | feature_cards / default |  | title, description, icon |
| Delivery Table | delivery_table / default | title, subtitle, content, deliverySource, deliveryNote, deliveryDestinationLabel, deliveryEstimateLabel, deliveryFeeLabel | title, deliveryEstimate, deliveryFee |
| Story with Steps | media_text_split / story-with-steps | title, subtitle, content, mediaUrl, mediaAlt, buttonText, buttonLink | title, description |
| Minimal FAQ | faq_accordion / default | title, subtitle, content | title, description |
| Help Panel | cta_strip / default | title, subtitle, content, buttonText, buttonLink |  |
| Policy Guidelines | policy_content / default | title, subtitle, content, policyNote | title, description, icon, linkUrl, buttonText, imageUrl |
| Policy Choice cards | policy_content / default | title, subtitle, content, policyNote | title, description, icon, linkUrl, buttonText, imageUrl |
| Policy Numbered steps | policy_content / default | title, subtitle, content, policyNote | title, description, icon, linkUrl, buttonText, imageUrl |
| Policy Process flow | policy_content / default | title, subtitle, content, policyNote | title, description, icon, linkUrl, buttonText, imageUrl |
| Policy Timeline | policy_content / default | title, subtitle, content, policyNote | title, description, icon, linkUrl, buttonText, imageUrl |
| Policy Tabbed guidelines | policy_content / default | title, subtitle, content, policyNote | title, description, icon, linkUrl, buttonText, imageUrl, category |
| Policy Editorial columns | policy_content / default | title, subtitle, content, policyNote | title, description, icon, linkUrl, buttonText, imageUrl |
| Policy Document | policy_content / default | title, subtitle, content, policyNote | title, description, icon, linkUrl, buttonText, imageUrl |
| Policy Directory | policy_content / default | title, subtitle, content, policyNote | title, description, icon, linkUrl, buttonText, imageUrl |
