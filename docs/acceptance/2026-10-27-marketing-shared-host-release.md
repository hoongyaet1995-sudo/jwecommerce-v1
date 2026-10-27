# Marketing Studio shared-host release

User authorized synchronization to GitHub main and Hostinger. This release installs the Marketing Studio source/UI/data schema and Admin entry/employee permission controls. Production execution is disabled by MARKETING_EXECUTION_ENABLED=false. No Node/FFmpeg/Chromium or paid provider installation/configuration is performed. Existing storefront and catalog data remain unchanged; no local experimental data/media is imported.

Validation:62 focused template/hosting/permission tests334assertions;4 JS state tests;383PHP syntax checks;isolated baseline Admin build;fresh local MySQL20migrations38tables. Source scan found no credential patterns. Private production database backup was taken before changes.

Limit: full historical Marketing regression is not green. Old material-pilot fixture calls are rejected by the hard-coded2026-10-27 expiry. At least12 unexpected failures observed for this cause; broader run stopped at the escalation threshold. No expiry/fee guard removed or renewed, no further affected repair/retry. Other historical failures were not fully classified; no claim of full functional acceptance. These execution flows remain disabled in this release. Re-enable only after explicit renewed approval, provider/runtime/queue validation and resolved regressions.

VPS handoff: install verified runtime separately;configure Node/Chromium/FFmpeg/FFprobe/Python modules through environment;provide a durable queue worker and scheduler;verify known-result recovery and bounded billing;then explicitly enable execution. Do not copy Windows binary paths into production or install tools on shared hosting.

Safety gates:unauthorized staff cannot view Studio;projects/media stay user-private;only owner changes Marketing grants;source/catalog preparation remains available;unapproved generation requests are rejected423 before queue/provider dispatch. Admin bundles were compiled from tracked baseline plus only the approved Marketing entry/permission changes, excluding other dirty UI work.
