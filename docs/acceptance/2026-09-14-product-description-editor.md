# Product description editor — local acceptance

Scope approved: replace the raw HTML textarea in ProductEditorModal with a visually editable description while preserving the existing neutral modal layout. No Google AI Studio redesign required.

- Tiptap handles prose, bold, italic, lists, links, undo and redo. Existing images and tables have schema support. The editor emits HTML through the existing descriptionHtml contract; merely opening it does not emit a change.
- CatalogApiController sanitizes saved description HTML with HTML Purifier; executable markup, event attributes and unsafe URL schemes are removed.
- Frontend regression: 1 passing test for existing HTML rendering without a spurious change.
- Sanitizer regression: 1 passing test, 5 assertions.
- Existing CatalogApiTest and ProductPdpContentPersistenceTest: 15 tests, 115 assertions passed.
- Admin Vite build passed. Full TypeScript checking is not green; it reports errors across multiple other modules.
- Authenticated localhost browser: edited local QA product JW QA Test Multiwarehouse Box 0913, added a bold Rich text acceptance 0914 paragraph, saved, reloaded, and reopened. Text and bold formatting remained. This was a draft test product, not an online edit.
- Not deployed. A release must include Composer installation for the newly locked HTML Purifier dependency as well as the Admin build. Keep unrelated pending WMS work out of this release.

## Storefront consumption follow-up

- Found PDP did not render the description; added a sanitized description using the existing details/accordion presentation.
- PLP grid/list and collection copy plus quick-add use a plain-text excerpt, preferring summary when provided. HTML entities are decoded and block boundaries retain spacing.
- A regression writes descriptionHtml through the authenticated catalog API, then checks the public PDP for strong/list markup and the public PLP for plain copy without escaped tags.
- Catalog API, storefront presentation fallback and sanitizer tests: 18 passed, 131 assertions. Storefront build passed (local Node version warning remains).
- Browser checked desktop and 390px mobile PDP/PLP with the actual locally edited QA product: bold paragraph appeared on PDP and prose excerpt appeared on PLP. QA product temporarily active for verification, restored to draft afterward. Its existing PDP image failed to load; description verification does not establish media health.
- These changes are local only and not included in the prior style release.

Reference: https://tiptap.dev/docs/editor/getting-started/install/react
