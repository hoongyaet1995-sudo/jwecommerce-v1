# Import review neutral style — local acceptance

- Source archive: `C:/Users/User/Downloads/jw-ecommerce-dashboard (36).zip`
- SHA256: `5435213C00E6B9E8BBA4E8536D0677D577597061B2EB449C910B0DA6DF5B5C42`
- Style reference: archive `src/components/marketplace/ProductMappingModal.tsx` (neutral dark surfaces).
- Target: `resources/admin/src/previews/marketplace32/components/marketplace/ImportProductReviewModal.tsx`.
- Archive import review still contains slate colors and unsupported `dark:bg-slate-850`; it does not already contain the requested neutral styling. This is a bounded adaptation of the supplied mapping modal palette, authorized by the user, not a verbatim replacement of the import component.
- Scope: neutral backgrounds, borders and text; replace unsupported dark table background; viewport height containment, non-shrinking header/footer, internal scrolling. Existing DOM, import data, validation, submission, and inventory semantics retained.
- Both single and batch imports use the same connected review component.

## Verification

- Existing ImportProductReviewModal and ConnectedImportReviewModal Vitest suites: 5 tests passed.
- Admin production build completed successfully (existing large-chunk warning).
- Authenticated Chrome at `http://127.0.0.1:8500/admin/marketplace/mapping`: opened a one-product import review in dark mode. Observed neutral dark container and table rows, readable product title and SKU, and visible cancel/confirm footer. No import was submitted.
- This evidence covers the local build, not a live release. The workspace contains other pending functional changes; those must not be included inadvertently in a styling-only release.
