# Marketplace release acceptance — 2026-11-13

Authorized scope: publish the accepted eight-section Marketplace Order Management page on www, update two homepage entry points and header/footer navigation, register Connected Store Operations generic Page Template, synchronize shared Sections and accepted fixes to www/ecom. Do not replace ecom homepage or include unrelated dirty work.

Source provenance: all31 existing shared files touched during Marketplace integration matched origin/main at their first local snapshot; applied final approved increments in an isolated release checkout based on f59d5bc35142f90dbffca1c3d68ad6e8ea8cad06. Added only required controls/presets/tests, portable nine assets, guarded release bundle/scripts and generic starter registration.

Validation:14focusedfrontend tests;6PHPtests/117assertions; clean scoped client+SSR build. Isolated SQLite rehearsal seeded current www published pages and menus, then preflight→publish→repeat passed; expected8sections, two homepage links, two menu links, exact +RM100 / store / month and original terms. ecom receives code/assets only; www publication script refuses other application roots.

Generic template uses independent manual contact pricing, no cross-merchant document source. Existing five generic starter JSON snapshots preserved before appended new entry. Source artwork/animations remain accepted native/lossless files.

Deployment requires this exact staged tree, GitHub main fast-forward, both Hostinger roots exact SHA, restricted public asset install on www, guarded www content publication and post-release ecom homepage equality. Actual receipts and SHA/HTTPS evidence recorded under local output/marketplace-oms/release; no secrets committed.
