# TypeScript 归因与修复：本地验收（2026-11-15）

本轮扫描、修复和回归已完成；全项目 TypeScript **仍不通过**，剩余117条。没有部署、提交或推送。开发只在 `C:/xampp/htdocs/jwstudio-ecommerce`；HEAD `ea7ce7a041bf8409cd114fe1fbf02742a94918e1` 保持不变。

## 当前结果

| 归因 | 本轮开始 | 已修复 | 剩余 |
|---|---:|---:|---:|
| 本次接入新增 | 4 | 4 | 0 |
| 接入前已有证据（参考快照+旧日志） | 143 | 27 | 116 |
| 历史证据不足 | 4 | 3 | 1 |
| 合计 | 151 | 34 | 117 |

涉及文件由76减至53。`src/cms/`、`src/components/pages/`、`src/components/menus/` 及本轮新增共享模块没有剩余诊断。没有修改tsconfig、关闭规则、排除文件或用ts-ignore/any压掉错误。上轮160条日志中的9项已在上一阶段修正；本轮新扫描151条确认它们未再出现，未将其重复计入本轮34项修复。

## 如何区分新旧

- 接入前baseline只有部分文件完整哈希，不能把dirty文件或当前Git差异直接当作本次新增。
- 对只读参考worktree运行虚拟文件系统TypeScript编译，复用当前同版本编译器/依赖；没有修改参考目录。8个baseline哈希中5个一致（Inspector、Renderer、Types、Typography、schema），另外3个不同，所以明确为部分参考。
- 比对文件、错误代码和底层原因；仅规范化字符串联合顺序、截断属性数量。6项诊断的展开文本变化较大，逐项核对相同根因，并在JSON中记录人工证据。
- 找到2026-10-30的 `output/cms-copy-typecheck-final.txt`，补齐Support旧预览、SVG/Meta测试夹具的8项历史证据，文件哈希和日志行号另存。
- 仍有1项ReportsBusiness本地tuple算术错误，文件在原dirty清单中，但没有完整历史内容快照；保持“未归因”，不声称它由接入引入，也没有修改它。

## 本次新增4项（全部已修）

| 模块 | 根因 | 修正 |
|---|---|---|
| imageTargets / connectedFamilies（2条） | 主图和条目图片的推断联合中，并非每个分支都有itemId | 明确统一目标结构，itemId可选，field为受限字面量 |
| ShellDesignEditor测试（2条） | 初始soft/dark字面量让React状态过窄，不能接收合法ShellDesign更新 | 状态按真实ShellDesign契约声明，测试断言保留 |

## 相关CMS修复

- Motion：原生drag/animation事件签名与Motion手势/生命周期冲突；补齐React19 ref类型。动画值、触发逻辑、DOM没有改变。
- 受支持的image-cards-contrast已在schema/renderer存在，但PageSection类型漏项；补齐类型，不扩大注册目录。
- Slider上传回调的videoUrl类型漏项；只移除Slider分支不可能成立的Logo判断，一般条目里的Partners Logo隐藏规则保留。
- 旧showcase模板在ES2022目标下调用findLast；用不修改原数组的reverse/find保留同样的最后CTA选择。
- Corporate工厂及测试夹具采用准确的上下文类型，修正窄状态、DOM按钮类型、editing必需参数和无效测试枚举；没有改作者保存值。

## 本地回归与保留验证

- 专项20文件113测试通过；最后挂载Inspector回归3测试通过，覆盖224实例（161注册+63区域）、223直接编辑及Chapter Navigation派生标签，含规范化/刷新/实际渲染和排除字段检查。
- 161个默认Section文档**逐字节相同**，SHA256 `53a703076455d1a8171f7db2304e9c635c4c279af00c24d5ca5d618045936879`。
- PHP隔离数据库3测试1127断言通过，验证创建、保存、锁版本、重新读取和前台runtime；不使用真实商店数据库写入。
- 全量161控件效应诊断1测试通过；它是SSR差异诊断，不把每一项差异当作全面视觉结论。原规划effects.json已备份/恢复，最新结果仅存本轮audit目录。
- Motion、shared image targets、Section类型修正前后生成的JavaScript逐字相同。
- 浏览器桌面1536px及手机390px：8页63区域和原161布局页面正常载入，无不可用提示/横向溢出；主图目标、画布菜单、套餐账期联动正常，控制台error为空。未修改商店资料或CMS内容。
- 最终production build通过；仍有既有资源/chunk size提示，详见final-build.log。
- 8个本地QA草稿内容哈希与接入完成时一致。21个TS/TSX文件为计划内变更，其他已捕获源文件哈希无额外变化；没有schema迁移、没有重写旧文档。

## 剩余问题的处理建议

剩余116条有接入前证据，集中在Shipping、Marketplace、Support、Catalog、Admin context及旧预览/测试夹具；这是后续模块清理范围，没有随本轮批量改动。建议按实际业务模块逐一修复契约并回归，不用全局类型放宽掩盖。

唯一未能严格归因的错误：`src/previews/reportsBusiness/Sample.tsx` 的 `stages` 混合数组推断使n成为string或number，随后参与除法（TS2362）。它没有CMS依赖，文件本轮未变。后续在Reports sample阶段明确元组数据类型并验证图表数值。

## 全部151项清单（行号为本轮初扫位置）

每一项的完整消息、来源证据和状态见 `output/jw-cms-integration-20261114/typescript-audit/attribution-initial.json`；剩余项完整消息见该目录的 `attribution-final.json`。

| # | 文件:行 | 错误 | 归因 | 状态 |
|---:|---|---|---|---|
| 1 | `src/cms/approvedArtworkGeometry.test.tsx:12` | TS2322 | 接入前参考编译已存在 | 已修复 |
| 2 | `src/cms/connectedShowcaseTemplates.ts:16` | TS2550 | 接入前参考编译已存在 | 已修复 |
| 3 | `src/cms/controlEffectAudit.test.tsx:49` | TS2322 | 缺少历史证据 | 已修复 |
| 4 | `src/cms/controlEffectAudit.test.tsx:49` | TS2322 | 缺少历史证据 | 已修复 |
| 5 | `src/cms/corporateLanding.ts:17` | TS2322 | 接入前参考编译已存在 | 已修复 |
| 6 | `src/cms/illustratedCta.test.tsx:4` | TS2741 | 接入前参考编译已存在 | 已修复 |
| 7 | `src/cms/illustratedCta.test.tsx:4` | TS2741 | 接入前参考编译已存在 | 已修复 |
| 8 | `src/cms/illustratedCta.test.tsx:4` | TS2741 | 接入前参考编译已存在 | 已修复 |
| 9 | `src/cms/illustratedCtaRight.test.tsx:2` | TS2741 | 接入前参考编译已存在 | 已修复 |
| 10 | `src/cms/imageCardsThree.ts:28` | TS2322 | 接入前参考编译已存在 | 已修复 |
| 11 | `src/cms/imageTargets.ts:42` | TS2339 | 本次接入新增 | 已修复 |
| 12 | `src/cms/imageTargets.ts:42` | TS2339 | 本次接入新增 | 已修复 |
| 13 | `src/components/catalog/ProductAdmin.tsx:84` | TS2554 | 接入前参考编译已存在 | 保留 |
| 14 | `src/components/catalog/ProductAdmin.tsx:294` | TS2322 | 接入前参考编译已存在 | 保留 |
| 15 | `src/components/catalog/ProductExtraInfoEditor.tsx:1075` | TS2339 | 接入前参考编译已存在 | 保留 |
| 16 | `src/components/catalog/ProductReviewsAdmin.tsx:869` | TS2339 | 接入前参考编译已存在 | 保留 |
| 17 | `src/components/dashboard/DashboardOverview.tsx:423` | TS2339 | 接入前参考编译已存在 | 保留 |
| 18 | `src/components/dashboard/DashboardOverview.tsx:423` | TS2339 | 接入前参考编译已存在 | 保留 |
| 19 | `src/components/dev/DeveloperSpecs.tsx:25` | TS2554 | 接入前参考编译已存在 | 保留 |
| 20 | `src/components/layout/NotificationDrawer.tsx:84` | TS2741 | 接入前参考编译已存在 | 保留 |
| 21 | `src/components/marketplace/MarketplaceAuthPage.tsx:121` | TS2322 | 接入前参考编译已存在 | 保留 |
| 22 | `src/components/marketplace/MarketplaceHub.tsx:29` | TS2339 | 接入前参考编译已存在 | 保留 |
| 23 | `src/components/marketplace/MarketplaceHub.tsx:30` | TS2339 | 接入前参考编译已存在 | 保留 |
| 24 | `src/components/marketplace/MarketplaceInventoryPage.tsx:346` | TS2339 | 接入前参考编译已存在 | 保留 |
| 25 | `src/components/menus/ShellDesignEditor.test.tsx:10` | TS2322 | 本次接入新增 | 已修复 |
| 26 | `src/components/menus/ShellDesignEditor.test.tsx:10` | TS2345 | 本次接入新增 | 已修复 |
| 27 | `src/components/orders/CommerceHub.tsx:32` | TS2339 | 接入前参考编译已存在 | 保留 |
| 28 | `src/components/pages/FocusedStepsInspector.test.tsx:10` | TS2322 | 接入前参考编译已存在 | 已修复 |
| 29 | `src/components/pages/ImageCardsThreeInspector.test.tsx:19` | TS2322 | 缺少历史证据 | 已修复 |
| 30 | `src/components/pages/ImageFlowInspector.test.tsx:10` | TS2322 | 接入前参考编译已存在 | 已修复 |
| 31 | `src/components/pages/KnowledgeTriptychInspector.test.tsx:10` | TS2322 | 接入前参考编译已存在 | 已修复 |
| 32 | `src/components/pages/motion/CmsMotionWrapper.tsx:237` | TS2322 | 接入前参考编译已存在 | 已修复 |
| 33 | `src/components/pages/motion/CmsMotionWrapper.tsx:291` | TS2322 | 接入前参考编译已存在 | 已修复 |
| 34 | `src/components/pages/motion/CmsMotionWrapper.tsx:327` | TS2322 | 接入前参考编译已存在 | 已修复 |
| 35 | `src/components/pages/motion/CmsMotionWrapper.tsx:361` | TS2322 | 接入前参考编译已存在 | 已修复 |
| 36 | `src/components/pages/motion/CmsMotionWrapper.tsx:399` | TS2322 | 接入前参考编译已存在 | 已修复 |
| 37 | `src/components/pages/PageTemplateModal.test.tsx:22` | TS2339 | 接入前参考编译已存在 | 已修复 |
| 38 | `src/components/pages/PageTemplateModal.test.tsx:23` | TS2339 | 接入前参考编译已存在 | 已修复 |
| 39 | `src/components/pages/PlanComparisonInspector.test.tsx:6` | TS2322 | 接入前参考编译已存在 | 已修复 |
| 40 | `src/components/pages/ScrollStackCardsInspector.test.tsx:10` | TS2322 | 接入前参考编译已存在 | 已修复 |
| 41 | `src/components/pages/SectionEyebrow.test.tsx:38` | TS2339 | 接入前参考编译已存在 | 已修复 |
| 42 | `src/components/pages/SectionInspector.tsx:2574` | TS2345 | 接入前参考编译已存在 | 已修复 |
| 43 | `src/components/pages/SectionInspector.tsx:2606` | TS2367 | 接入前参考编译已存在 | 已修复 |
| 44 | `src/components/pages/SectionRenderer.tsx:1765` | TS2322 | 接入前参考编译已存在 | 已修复 |
| 45 | `src/components/pages/ShippingSections.test.tsx:26` | TS2322 | 接入前参考编译已存在 | 已修复 |
| 46 | `src/components/pages/ShopNavigation.test.tsx:14` | TS2322 | 接入前参考编译已存在 | 已修复 |
| 47 | `src/components/pages/TeamStoryOverviewInspector.test.tsx:10` | TS2322 | 接入前参考编译已存在 | 已修复 |
| 48 | `src/components/settings/NotificationTemplateSettingAdmin.tsx:355` | TS2345 | 接入前参考编译已存在 | 保留 |
| 49 | `src/components/settings/ShopSettingsHub.tsx:621` | TS2367 | 接入前参考编译已存在 | 保留 |
| 50 | `src/components/shipping/CorrectFulfilmentModal.tsx:366` | TS2339 | 接入前参考编译已存在 | 保留 |
| 51 | `src/components/shipping/EasyParcelBookingModal.test.ts:86` | TS2769 | 接入前参考编译已存在 | 保留 |
| 52 | `src/components/shipping/EasyParcelBookingModal.test.ts:137` | TS2769 | 接入前参考编译已存在 | 保留 |
| 53 | `src/components/shipping/EasyParcelBookingModal.test.ts:185` | TS2769 | 接入前参考编译已存在 | 保留 |
| 54 | `src/components/shipping/PickupScheduleModal.tsx:56` | TS2322 | 接入前参考编译已存在 | 保留 |
| 55 | `src/components/shipping/PickupScheduleModal.tsx:62` | TS2322 | 接入前参考编译已存在 | 保留 |
| 56 | `src/components/shipping/ShippingDashboard.test.ts:369` | TS2769 | 接入前参考编译已存在 | 保留 |
| 57 | `src/components/shipping/ShippingDashboard.test.ts:501` | TS2769 | 接入前参考编译已存在 | 保留 |
| 58 | `src/components/shipping/ShippingDashboard.test.ts:567` | TS2769 | 接入前参考编译已存在 | 保留 |
| 59 | `src/components/shipping/ShippingDashboard.test.ts:577` | TS2322 | 接入前参考编译已存在 | 保留 |
| 60 | `src/components/shipping/ShippingDashboard.test.ts:658` | TS2769 | 接入前参考编译已存在 | 保留 |
| 61 | `src/components/shipping/ShippingDashboard.test.ts:794` | TS2769 | 接入前参考编译已存在 | 保留 |
| 62 | `src/components/shipping/ShippingDashboard.test.ts:819` | TS2769 | 接入前参考编译已存在 | 保留 |
| 63 | `src/components/shipping/ShippingDashboard.test.ts:898` | TS2769 | 接入前参考编译已存在 | 保留 |
| 64 | `src/components/shipping/ShippingDashboard.test.ts:1131` | TS2769 | 接入前参考编译已存在 | 保留 |
| 65 | `src/components/shipping/ShippingDashboard.test.ts:1193` | TS2769 | 接入前参考编译已存在 | 保留 |
| 66 | `src/components/shipping/ShippingDashboard.test.ts:1227` | TS2769 | 接入前参考编译已存在 | 保留 |
| 67 | `src/components/shipping/ShippingDashboard.test.ts:1236` | TS2769 | 接入前参考编译已存在 | 保留 |
| 68 | `src/components/shipping/ShippingDashboard.test.ts:1242` | TS2769 | 接入前参考编译已存在 | 保留 |
| 69 | `src/components/shipping/ShippingDashboard.test.ts:1250` | TS2769 | 接入前参考编译已存在 | 保留 |
| 70 | `src/components/shipping/ShippingDashboard.test.ts:1257` | TS2769 | 接入前参考编译已存在 | 保留 |
| 71 | `src/components/shipping/ShippingDashboard.test.ts:1271` | TS2769 | 接入前参考编译已存在 | 保留 |
| 72 | `src/components/shipping/ShippingDashboard.test.ts:1282` | TS2769 | 接入前参考编译已存在 | 保留 |
| 73 | `src/components/shipping/ShippingOverviewTab.tsx:334` | TS2339 | 接入前参考编译已存在 | 保留 |
| 74 | `src/components/shipping/ShippingOverviewTab.tsx:334` | TS2339 | 接入前参考编译已存在 | 保留 |
| 75 | `src/components/shipping/SmartRoutingRuleModal.tsx:32` | TS2339 | 接入前参考编译已存在 | 保留 |
| 76 | `src/components/shipping/SmartRoutingTab.tsx:189` | TS2339 | 接入前参考编译已存在 | 保留 |
| 77 | `src/components/shipping/SmartRoutingTab.tsx:459` | TS2339 | 接入前参考编译已存在 | 保留 |
| 78 | `src/components/shipping/SmartRoutingTab.tsx:459` | TS2339 | 接入前参考编译已存在 | 保留 |
| 79 | `src/components/shipping/SmartRoutingTab.tsx:459` | TS2339 | 接入前参考编译已存在 | 保留 |
| 80 | `src/components/support/ChatWorkspacePanel.tsx:81` | TS2345 | 接入前参考编译已存在 | 保留 |
| 81 | `src/components/support/ChatWorkspacePanel.tsx:154` | TS2367 | 接入前参考编译已存在 | 保留 |
| 82 | `src/components/support/CustomerContextPanel.tsx:117` | TS2339 | 接入前参考编译已存在 | 保留 |
| 83 | `src/components/support/CustomerContextPanel.tsx:120` | TS2339 | 接入前参考编译已存在 | 保留 |
| 84 | `src/components/support/CustomerContextPanel.tsx:120` | TS2339 | 接入前参考编译已存在 | 保留 |
| 85 | `src/components/support/CustomerContextPanel.tsx:123` | TS2339 | 接入前参考编译已存在 | 保留 |
| 86 | `src/components/support/CustomerContextPanel.tsx:126` | TS2339 | 接入前参考编译已存在 | 保留 |
| 87 | `src/context/AdminContext.tsx:1829` | TS2345 | 接入前参考编译已存在 | 保留 |
| 88 | `src/context/AdminContext.tsx:3315` | TS2353 | 接入前参考编译已存在 | 保留 |
| 89 | `src/context/AdminContext.tsx:3315` | TS2353 | 接入前参考编译已存在 | 保留 |
| 90 | `src/data/defaultAIAutoReply.ts:17` | TS2741 | 接入前参考编译已存在 | 保留 |
| 91 | `src/integration/publishingReviewSession.test.ts:43` | TS2339 | 接入前参考编译已存在 | 保留 |
| 92 | `src/integration/publishingReviewSession.test.ts:64` | TS2339 | 接入前参考编译已存在 | 保留 |
| 93 | `src/integration/shipmentData.test.tsx:442` | TS2322 | 接入前参考编译已存在 | 保留 |
| 94 | `src/integration/shipmentData.test.tsx:479` | TS2322 | 接入前参考编译已存在 | 保留 |
| 95 | `src/integration/storeInventory.test.ts:108` | TS2339 | 接入前参考编译已存在 | 保留 |
| 96 | `src/integration/storeInventory.test.ts:109` | TS2339 | 接入前参考编译已存在 | 保留 |
| 97 | `src/marketplace/components/marketplace/MarketplaceAuthPage.tsx:76` | TS2322 | 接入前参考编译已存在 | 保留 |
| 98 | `src/marketplace/components/marketplace/MarketplaceInventoryPage.tsx:327` | TS2339 | 接入前参考编译已存在 | 保留 |
| 99 | `src/marketplace/OperationalMapping.tsx:43` | TS2345 | 接入前参考编译已存在 | 保留 |
| 100 | `src/marketplace/OperationalMapping.tsx:93` | TS2322 | 接入前参考编译已存在 | 保留 |
| 101 | `src/marketplace/OperationalMapping.tsx:95` | TS2322 | 接入前参考编译已存在 | 保留 |
| 102 | `src/marketplace/OperationalMapping.tsx:105` | TS2322 | 接入前参考编译已存在 | 保留 |
| 103 | `src/marketplace/OperationalMapping.tsx:135` | TS2322 | 接入前参考编译已存在 | 保留 |
| 104 | `src/marketplace/OperationalMapping.tsx:136` | TS2322 | 接入前参考编译已存在 | 保留 |
| 105 | `src/previews/inventory39/components/marketplace/inventory/IndependentStockAllocationModal.tsx:344` | TS2367 | 接入前参考编译已存在 | 保留 |
| 106 | `src/previews/inventory39/components/marketplace/inventory/IndependentStockAllocationModal.tsx:789` | TS2304 | 接入前参考编译已存在 | 保留 |
| 107 | `src/previews/inventory39/components/marketplace/MarketplaceInventoryPage.tsx:82` | TS2339 | 接入前参考编译已存在 | 保留 |
| 108 | `src/previews/inventory39/components/marketplace/MarketplaceInventoryPage.tsx:1294` | TS2339 | 接入前参考编译已存在 | 保留 |
| 109 | `src/previews/inventory48/components/marketplace/inventory/StockTakeRecoveryFlow.tsx:115` | TS2339 | 接入前参考编译已存在 | 保留 |
| 110 | `src/previews/inventory48/components/marketplace/inventory/StoreStockTransferStatus.test.tsx:64` | TS2739 | 接入前参考编译已存在 | 保留 |
| 111 | `src/previews/marketplace28/components/marketplace/MarketplaceAuthPage.tsx:101` | TS2322 | 接入前参考编译已存在 | 保留 |
| 112 | `src/previews/marketplace28/components/marketplace/MarketplaceInventoryPage.tsx:327` | TS2339 | 接入前参考编译已存在 | 保留 |
| 113 | `src/previews/marketplace28/context/AdminContext.tsx:268` | TS2345 | 接入前参考编译已存在 | 保留 |
| 114 | `src/previews/marketplace30/components/marketplace/MarketplaceInventoryPage.tsx:328` | TS2339 | 接入前参考编译已存在 | 保留 |
| 115 | `src/previews/marketplace30/context/AdminContext.tsx:349` | TS2345 | 接入前参考编译已存在 | 保留 |
| 116 | `src/previews/marketplace34/adjustmentInteraction.test.tsx:18` | TS2741 | 接入前参考编译已存在 | 保留 |
| 117 | `src/previews/marketplace34/components/marketplace/StoreFulfillmentBindingModal.tsx:150` | TS2339 | 接入前参考编译已存在 | 保留 |
| 118 | `src/previews/marketplace34/components/marketplace/StoreFulfillmentBindingModal.tsx:150` | TS2339 | 接入前参考编译已存在 | 保留 |
| 119 | `src/previews/marketplace34/components/marketplace/StoreWarehouseBindingTab.tsx:159` | TS2339 | 接入前参考编译已存在 | 保留 |
| 120 | `src/previews/marketplace34/components/marketplace/StoreWarehouseBindingTab.tsx:159` | TS2339 | 接入前参考编译已存在 | 保留 |
| 121 | `src/previews/marketplace34/components/marketplace/WarehouseTransferTab.tsx:272` | TS2339 | 接入前参考编译已存在 | 保留 |
| 122 | `src/previews/marketplace34/components/marketplace/WarehouseTransferTab.tsx:275` | TS2339 | 接入前参考编译已存在 | 保留 |
| 123 | `src/previews/marketplace36/components/marketplace/InventoryAdjustmentModal.tsx:66` | TS2339 | 接入前参考编译已存在 | 保留 |
| 124 | `src/previews/marketplace36/components/marketplace/MarketplaceInventoryPage.tsx:194` | TS2339 | 接入前参考编译已存在 | 保留 |
| 125 | `src/previews/marketplace36/components/marketplace/MarketplaceInventoryPage.tsx:197` | TS2339 | 接入前参考编译已存在 | 保留 |
| 126 | `src/previews/marketplace36/components/marketplace/MarketplaceInventoryPage.tsx:197` | TS2339 | 接入前参考编译已存在 | 保留 |
| 127 | `src/previews/marketplace36/components/marketplace/MarketplaceInventoryPage.tsx:197` | TS2339 | 接入前参考编译已存在 | 保留 |
| 128 | `src/previews/marketplace36/components/marketplace/MarketplaceInventoryPage.tsx:199` | TS2345 | 接入前参考编译已存在 | 保留 |
| 129 | `src/previews/marketplace36/components/marketplace/MarketplaceInventoryPage.tsx:199` | TS2339 | 接入前参考编译已存在 | 保留 |
| 130 | `src/previews/marketplace36/components/marketplace/ModeAndWarehouseConfigTab.tsx:315` | TS2339 | 接入前参考编译已存在 | 保留 |
| 131 | `src/previews/marketplace36/components/marketplace/ModeAndWarehouseConfigTab.tsx:318` | TS2339 | 接入前参考编译已存在 | 保留 |
| 132 | `src/previews/marketplace36/components/marketplace/ModeAndWarehouseConfigTab.tsx:318` | TS2339 | 接入前参考编译已存在 | 保留 |
| 133 | `src/previews/marketplace36/components/marketplace/ModeAndWarehouseConfigTab.tsx:318` | TS2339 | 接入前参考编译已存在 | 保留 |
| 134 | `src/previews/marketplace36/components/marketplace/ModeAndWarehouseConfigTab.tsx:467` | TS2339 | 接入前参考编译已存在 | 保留 |
| 135 | `src/previews/marketplace36/components/marketplace/ModeAndWarehouseConfigTab.tsx:468` | TS2339 | 接入前参考编译已存在 | 保留 |
| 136 | `src/previews/marketplace36/components/marketplace/ModeAndWarehouseConfigTab.tsx:468` | TS2339 | 接入前参考编译已存在 | 保留 |
| 137 | `src/previews/marketplace36/components/marketplace/StoreFulfillmentBindingModal.tsx:154` | TS2339 | 接入前参考编译已存在 | 保留 |
| 138 | `src/previews/marketplace36/components/marketplace/StoreFulfillmentBindingModal.tsx:154` | TS2339 | 接入前参考编译已存在 | 保留 |
| 139 | `src/previews/marketplace36/components/marketplace/StoreWarehouseBindingTab.tsx:159` | TS2339 | 接入前参考编译已存在 | 保留 |
| 140 | `src/previews/marketplace36/components/marketplace/StoreWarehouseBindingTab.tsx:159` | TS2339 | 接入前参考编译已存在 | 保留 |
| 141 | `src/previews/marketplace36/components/marketplace/WarehouseTransferTab.tsx:272` | TS2339 | 接入前参考编译已存在 | 保留 |
| 142 | `src/previews/marketplace36/components/marketplace/WarehouseTransferTab.tsx:275` | TS2339 | 接入前参考编译已存在 | 保留 |
| 143 | `src/previews/reportsBusiness/Sample.tsx:4` | TS2362 | 缺少历史证据 | 保留 |
| 144 | `src/previews/supportOriginal/components/support/CustomerContextPanel.tsx:104` | TS2339 | 历史日志已存在 | 保留 |
| 145 | `src/previews/supportOriginal/components/support/CustomerContextPanel.tsx:107` | TS2339 | 历史日志已存在 | 保留 |
| 146 | `src/previews/supportOriginal/components/support/CustomerContextPanel.tsx:107` | TS2339 | 历史日志已存在 | 保留 |
| 147 | `src/previews/supportOriginal/components/support/CustomerContextPanel.tsx:110` | TS2339 | 历史日志已存在 | 保留 |
| 148 | `src/previews/supportOriginal/components/support/CustomerContextPanel.tsx:113` | TS2339 | 历史日志已存在 | 保留 |
| 149 | `tests/fixtures/batch-sync-stage-preview.tsx:6` | TS2307 | 历史日志已存在 | 保留 |
| 150 | `tests/fixtures/facebook-multi-picker.tsx:18` | TS2322 | 历史日志已存在 | 保留 |
| 151 | `tests/fixtures/facebook-picker.tsx:18` | TS2741 | 历史日志已存在 | 保留 |

## 证据目录

`output/jw-cms-integration-20261114/typescript-audit/`：raw logs、reference-diagnostics.json、historical-evidence.json、before-fixes/、defaults-preservation.json、draft-preservation.json、source-preservation.json、type-only-emission.json、browser-regression.json、inspector-regression.jpg。单一问题台账继续使用上级checkpoint.md。

未测量的总耗时/token用量 unavailable；不补造历史失败次数。
