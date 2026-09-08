# OMS source operational-mode guard

- Located existing `OrderInspectionDrawer` and its real detail adapter; reuse it during official OMS binding rather than a second custom drawer.
- ZIP 30 OMS accepts an operational flag. In that mode the inbound-order simulator is absent; export, AWB, packing and dispatch controls are disabled until actual adapters are connected. The demo dispatch handler also has a guard.
- Platform status text remains raw/readable rather than claiming READY_TO_SHIP means AWB printed. Source demo behavior remains unchanged outside operational mode.
- Operational mode does not mount the old demo detail drawer; the wrapper must mount the existing real inspection drawer.
- Test first failed for visible simulator; regression now passes for simulator exclusion, truthful READY_TO_SHIP and disabled AWB. TypeScript and diff checks passed.
- Official OMS runtime is not yet switched: data pagination, summary binding and lazy detail wrapper remain. No deployment.
