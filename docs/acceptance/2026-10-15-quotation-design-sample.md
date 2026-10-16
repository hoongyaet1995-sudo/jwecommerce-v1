# Generic quotation sample — design review only

User approved creating a sample of merchant quote drafting and customer quote viewing; asked for the feature's core value. Mode FIDELITY_PREVIEW. Existing Enquiries Admin sample is the visual foundation; Universal fonts/blue are reused for the customer document. This is not an operational quotation subsystem. No application routes, controllers, database, messages, orders, payments, configuration or deployments changed.

Entry: /page-template-sample/quotation.html; customer initial view: same URL#customer. All state stays in the page and resets on refresh. Public static sample fixtures are explicitly fictional and labelled Demo. Side navigation is context illustration, not a new Admin navigation implementation.

Prototype covers: three generic enquiry examples (custom goods, photography, logistics), editable items/details/quantity/unit price, live totals, expiry, customer-visible scope notes, draft save simulation, customer preview, send confirmation simulation, locked sent version, revision draft, retained earlier sent versions, quotation-reference discussion preview. No accept/payment controls in this phase. Sending and discussion are explicitly simulated.

Browser checks: goods total4410; quantity60 recalculates5200; photography4950; logistics1150. Zero quantity blocks preview. Sent v1 fields disabled; new v2 unit price99 yields5410; after simulated v2 send, retained v1 still opens4410. Discussion context retains QT-DEMO-001/v1/4410. Fresh customer console clean. Desktop screenshots visually inspected.390px Admin/customer viewports have no document horizontal overflow; customer item row becomes3columns. Metrics overrides cleared. Final tabs reset to default review data and retained.

Verification commands: node --check public/page-template-sample/quotation.js passed. No production build or database tests needed for isolated static prototype. Original source public/page-template-sample/enquiry-admin.html unchanged; owned CSS foundation copied to quotation-base.css, with scoped quotation.css additions. Original application dirty work preserved.

Issue ledger: one unexpected runtime failure in retained-version activity (activity array used instead of activity DOM container); root located through browser console, corrected with direct patch, then repeated full v1->v2->retained-v1 workflow succeeded. No blind reruns. Total1unexpected underlying issue resolved. Elapsed/token usage unavailable. Syntax check alone was insufficient; browser interaction caught this issue.

Artifacts: output/quotation-sample/{admin-desktop,customer-desktop,admin-mobile,customer-mobile}.png. Review URL is the deliverable. This sample is not synced or deployed and does not replace the approved operational Enquiries3A-3C flow. Quotation schema, sending channel, recipient authorization, expiry enforcement, revision persistence and future acceptance/payment require a separate approved integration phase.
