# Page Studio UI integration
Approved prototype: public/cms-page-studio-preview. Preserve its shell/CSS; replace illustrative content with canonical SectionRenderer and existing application state/actions. Local only; no paid provider, publish, commit/push/deploy.
1. Add focused primary-action state tests; preserve brief/question/generation gates.
2. Implement preview-first React screens and scoped prototype CSS. Reuse canonical text fields/image targets, metadata, media picker, Inspector, proposal and quality review. No placeholder catalog in official UI.
3. Bind existing controller handlers and workspace recovery; keep images stage visible during actual generation, retain successes on failure. Do not simulate provider progress in official UI.
4. Fake backend suite, frontend tests, build, lint changed-file diagnostic review, normal localhost fixture acceptance and screenshots. Preserve unrelated work.
Image aspect-ratio contract remains the existing provider support boundary in this UI phase; no unsupported shape offered as a clickable option. A separate renderer-backed geometry audit is still required.
