# Category hierarchy integration

Approved spec: public/category-hierarchy-preview/index.html and user approval 2026-11-02.

- [x] Preserve existing category editor and banner controls; direct-child navigation uses parent query, refresh/back retain location.
- [x] Protect maximum three levels, cycles, subtree reparent depth and deletion with children/products on API.
- [x] Add paginated product list/search (20/page), searchable paginated picker, narrow atomic link/unlink endpoints. A product has one primary category; moving is explicit.
- [x] Verify backend boundaries, guest access, pagination and unchanged product attributes; build and normal localhost page checks.
- [x] No commit, push or deployment.

Baseline: existing dirty workspace retained; HEAD recorded in acceptance log. No inventory behavior changes.
