# CMS directory delete fix
Baseline d3bf720. Root cause: directory returns before editor-only delete confirmation mounts. Preserve existing modal, API and homepage protection; move page confirmation into directory. No actual merchant page deletion authorized or performed. Initial regression red declared before run; tests structural branch contract, not browser proof. Browser control previously disabled by Work settings. Token/time unavailable.
Initial red reproduced missing modal in directory. Moved unchanged modal to directory branch; no API or styling changes. Run branch regression and production build after concrete fix.
Regression green (1 test), Vite build passed, scoped diff whitespace check passed. Browser retry reports local browser use disabled in Work settings; no visual acceptance or merchant deletion claimed. Local only, sync pending user approval.
User approved synchronization. Scoped release builds baseline d3bf720 plus PageAdmin modal relocation only; preserve unrelated dirty work.
Release staging encountered stale zero-byte git index.lock; confirmed no git process before removing lock. No source changes caused by lock. Scoped export build passed.
