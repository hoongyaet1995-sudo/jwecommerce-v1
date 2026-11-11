# Omnichannel and editable page template release

Approved scope: publish Omnichannel AI Support on www, update homepage support entries and header/footer, synchronize shared sections to www/ecom. Preserve every unrelated dirty development change; ecom CMS data/homepage must remain identical. Source assembled over origin/main 853483793f19780a29bcacf55b1246af72ee7cbf.

## Reusable template registry

| Template | Source design | Boundary |
|---|---|---|
| Connected Business | corporate landing | Editable sections, media, navigation; manual example plans |
| Business Showcase | Business Websites | Editable composite media, icons, steps, CTA, FAQ |
| Commerce & Catalogue | Ecommerce | Editable catalogue cards, process, workspace, connections, manual plans |
| Customer Conversations | Omnichannel AI Support | Editable channel images, knowledge, handover, stack, focused steps, plans and FAQ |

Each creation clones section/item/tab IDs and image-slot/anchor references. Page Settings own brand/typography, container inherits site width/gutter. No local page IDs or JW contact URLs in factories; final generic contact uses the existing support launcher. Sample prices require merchant editing and are deliberately independent of the actual published JW tariff. 61 original approved still images packaged as lossless WebP. Image Flow keeps three independently replaceable pictures, staggered motion and themed SVG arrows. Stack and Focused Steps are bounded presets, not new parallel control systems.

## Verification

- 27 focused frontend tests, 20 shared contract/render/style regression tests and 4 mounted Inspector round-trip tests passed (51 total).
- 11 PHP tests / 95 assertions passed; initial missing-environment fixture warnings eliminated with empty non-secret test .env; final run clean.
- Production frontend + SSR build passed; existing large-bundle and unrelated sample-asset warnings retained.
- Real local picker, template preview, create draft, edit plan title, exit/reopen and retained value verified. QA draft archived.
- Guarded publication rehearsed against isolated SQLite seeded with current www baseline: preflight, publication, source prices299/399 and exact billing/terms, then idempotent already-published success.
- Publisher restricted to www root; checks published/draft hashes and menus, backs up affected records before transaction, never changes homepage selection. ecom gets no content publisher.

## Issue ledger

No blind reruns. CTA self-anchor object mutation and old renderer contact action fixed with regression first. Missing accepted centered hero / gallery dispatch / fixed-slot helper in initial packaging corrected after explicit diagnostics. Sparse image-target and content capability parity completed without new controls. Existing test hardcoded73 presets and external output directory replaced by complete-role coverage and isolated temporary output. All resolved; no issue reached three consecutive unexpected failures. Historical elapsed time/token use unavailable.

Release SHA and remote receipts are recorded by Git history and storage/app/releases/omnichannel-20261111/receipt.json on www. Local release receipts/screenshots and ecom before/after preservation evidence stay in output/omnichannel-release, excluded from Git. No production credentials or QA drafts included.
