# JTG detail/gallery implementation plan

> Execute inline using superpowers:executing-plans, as requested by the user. Preserve unrelated dirty work. No release commits without sync approval.

**Goal:** Editable shared CMS detail/gallery layouts with working JTG local page navigation.
**Architecture:** Extend existing semantic section types and canonical ownership. Shared Builder/public renderer and Inspector; real catalog IDs, existing header/footer and page width.
**Tech Stack:** Laravel/MySQL, React/TypeScript, existing Tailwind CMS runtime.
**Spec:** docs/designs/2026-10-17-jtg-detail-gallery-design.md (approved).

## Global constraints

- Page Width for all foreground content; backgrounds may be full width.
- Main/sub color and Barlow/Inter registry; no per-page renderer or invented merchant facts.
- CMS Generator and similar-product image search excluded.

## Review focus

Sparse galleries, layout switching preserves data, filtered lightbox keyboard/focus, canonical ownership/publish and independently editable starter IDs.

## Tasks

- [x] Add focused layout/Inspector/contract tests, declare intentional red; update ledger before running.
- [x] Extend gallery/feature/rich-text/CTA presets, gallery options and per-item category ownership in types, schema, TS runtime and Inspector capability maps.
- [x] Implement focused shared industrial detail/gallery renderers, grouped incremental gallery and accessible lightbox. Existing section helpers own styling/media/actions.
- [x] Register Add Section starters and page factories/picker entries. Reuse actual catalog product listing source, durable illustrative media and local JTG page styling.
- [x] Test sparse Content and layout A/B/A, entries, canonical save/publish and existing logistics/template regressions; build Admin assets.
- [x] Create local independent drafts, verify Builder save/refresh and public preview, desktop/mobile, actions. Wire explicitly requested homepage card links after ready local pages. No production sync.

Private-media context is a separate approved spec and subsequent execution unit; its authorization gates and evidence must not be conflated with CMS completion.

Completion evidence: docs/acceptance/2026-10-17-jtg-faq-detail-private-context.md. Private plan real acceptance is image+orders, PDF+orders, image+quotation and waiting-enquiry PDF; ordinary nonempty cart/checkout automated only. Local permanent worker operation is not verified. No sync/deploy.
