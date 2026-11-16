# Warm editorial / Soft earth image and gradient acceptance

Local only, 2026-11-16. No commit, push or deployment. Canonical source C:/xampp/htdocs/jwstudio-ecommerce, branch codex/social-crm-identity, HEAD ea7ce7a041bf8409cd114fe1fbf02742a94918e1. Existing dirty work retained.

## Scope and outcome

All 42 layouts flagged by the expanded image/gradient diagnostic were visually inspected in Warm and Soft earth, Light and Contrast, source widths1280 and390:336 section states. Checks covered visible eyebrow, title, description, item title/description and rendered actions. Long mobile mosaic card46 reports1913px; its tail beyond the1900px contact-sheet cap was separately inspected in all4 palette/surface combinations. Source number indexes are zero-based; picker labels display index+1.

42 cards: 4, 18, 24, 33, 34, 35, 36, 37, 46, 47, 48, 49, 50, 51, 52, 53, 54, 55, 64, 69, 70, 71, 72, 74, 75, 78, 79, 80, 84, 91, 95, 99, 100, 102, 103, 104, 105, 127, 128, 129, 139, 144.

Default Neutral placeholders, existing decorative imagery and current default gradients were the image scope. No claim about all possible future uploaded images. Image text was reviewed as image text, never automatically passed by a solid-background approximation.

## Four confirmed visual defects repaired

- Connected Process: white sequence circles inherited light ink from alternating dark steps. Keep circle paint/geometry and use readable Dark Text for01–04. Actual step02 selected and inspected at desktop/mobile; computed01–04 all dark onwhite.
- Product Preview Tabs: selected Contrast tab had fixed white text on the light resolved primary-to-secondary gradient. Bind existing selected paint/ink to the resolved primary pair. No new control or DOM wrapper. Authored primary text remains authoritative (including deliberately unsafe authored colors).
- Split Hero floating product: Light surface inherited dark foreground over its existing85% dark caption. Register the existing local dark-panel context without changing its paint/opacity/geometry.
- Location & Map: Contrast inherited light map-placeholder text over neutral100. Register the existing light map context; placeholder text/icon remain visible.

Only application source changed in this phase: resources/admin/src/cms/defaultSectionColors.ts. Added one user-color preservation regression in its existing test file. Other source hashes from the preceding acceptance remain identical. Runtime assets regenerated locally. Every rule remains neutral-v1 scoped; unmarked legacy sections are untouched. No page saves or database writes.

## Hover and regression

168 valid pointer Hover records across12 layouts with rendered controls, both palettes, source widths1280 and390 and both surfaces. Deduplicated repeated controls by channel/kind/class/selected state, not fabricated per invisible CTA. Remaining30 flagged layouts had no recorded visible action state; their visible static text was inspected. Close-up screenshot review included mobile active tabs, mobile slider CTA, desktop billboard secondary CTA and map button. Image zoom/gradient decorative backgrounds were retained.

Important diagnostic correction: a direct-preview width query alone does not resize the tab. Initial request-width-only mobile Hover records are retained separately as diagnostic evidence, excluded from final acceptance. Mobile Hover was rerun with per-tab viewport override and innerWidth390 verified. The override was cleared afterwards. Contact-sheet mobile iframes were390px from the outset.

Final full regression: Warm173x4 and Soft earth173x4,0 rendering errors and0 solid-text failures. Fourteen existing focused tests passed, TypeScript passed, contract export/Vite/SSR build passed. One additional authored-tab-ink/legacy-scope preservation regression passed in the existing7-test color file. Build retains existing unrelated missing sample asset/font and bundle size warnings.

Data preservation:147 saved document hashes checked,146 identical and the same pre-existing document364 QA difference; no new data differences. User authored title#ffcc33 was confirmed after real saved-preview refresh. Other dirty modifications retained. Cumulative issue history9 and explicit continuation authorization remain in checkpoint; no counter reset.

## Evidence

output/palette-earth-acceptance-20261116/manual-photo/ contains manual-checklist.csv, manual-acceptance-summary.json, warm-full-regression.json, clay-full-regression.json, hover-states.json, corrected hover-mobile-390.json, contact sheets, long mosaic tails and Hover/rotation close-ups. Some Warm sheets predate the four-selector patch; affected cards were separately rechecked after the final build. No source or user data was changed during visual interactions.

Local review: http://127.0.0.1:8500/add-section-preview-sample/?sheet=1&cards=4,84,105,139&palette=warm&width=390
