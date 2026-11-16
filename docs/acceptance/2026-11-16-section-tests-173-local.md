# 39 条历史失败审计与 173 卡片本地回归

范围：本地 `C:/xampp/htdocs/jwstudio-ecommerce`。未提交、推送或部署，未操作 www/ecom/social。保留其他未完成修改。

## 结论

- 修改前复现：173 卡片综合测试 595 项，537 通过、58 失败；其中 39 项与接入前历史失败逐条对应，另外 19 项为新增家族暴露的旧测试假设。
- 历史 39 项：37 项测试桩／断言问题；2 项原有能力问题（Partner Logos 的描述字段声明、Image Flow 的条目编辑入口）。39/39 对应案例现已通过。
- 新暴露 19 项：缺少分组归属、slot 字段、有效枚举、条件字段状态和实际按钮名称的测试兼容问题。19/19 对应案例现已通过；新增稳定 `data-inspector-item` 定位，不改变结构和样式。
- 最终前端：7 个文件、626 项测试通过，0 失败，包括全部 173 卡片挂载／内容能力、条目操作、渲染、新共享家族和定价回归。
- PHP：173 卡片新增→编辑→保存→刷新→前台 runtime 数据，以及 19 个新布局发布 payload，两项测试、583 个断言通过；使用 SQLite 内存库，原有页面 hash 保持一致。
- TypeScript 最终检查通过；客户端与 SSR 构建通过（12.73s）。既有未解析资源／大包构建提示仍保留，不冒充已修复。

## 实际应用修改

1. `repeatedItemContract.ts`：仅 Partner Logos 不再声明前台不消费的 description；classic Logo 描述仍可用。旧数据不删除，编辑其他字段时保留已有 description。官方 exporter 生成的 AI copy manifest 只改变 logo_cloud 的 partners 能力。
2. `SectionInspector.tsx`：Image Flow 使用已有图片条目编辑与 flow 自动重排能力，开放增删排序；仅用户编辑条目时进入 flow。未操作时仍保留三图默认布局。没有改动 CSS、渲染布局、原 161 卡片默认数据。
3. `ConnectedFamilyContent.tsx`：给现有条目节点补标准定位属性，供分组 Passport 的真实操作测试使用。
4. 综合测试保留全 173 卡片，补齐 pages mock、合法枚举、条件 pricing 和稀疏 connected 标识；检查实际支持的字段，不再要求未渲染的控件。没有排除新卡片或降低为仅能挂载的测试。

## 逐项历史清单

| # | 测试案例 | 根因 | 处理 |
|---|---|---|---|
| 1 | mounts 'Plan Comparison' with applicable controls and three categories | 测试桩遗漏 pages | 已修复并通过 |
| 2 | all textual Delete and Clear actions use accessible icon buttons | 测试桩遗漏 pages | 已修复并通过 |
| 3 | registers all twelve media text layouts as independently selectable starters | 布局清单断言过时 | 已修复并通过 |
| 4 | exposes empty supported Content fields for editorial-hero without pre-existing saved keys | Subtitle → Eyebrow 断言过时 | 已修复并通过 |
| 5 | exposes empty supported Content fields for editorial-story without pre-existing saved keys | Subtitle → Eyebrow 断言过时 | 已修复并通过 |
| 6 | exposes empty supported Content fields for editorial-cover without pre-existing saved keys | Subtitle → Eyebrow 断言过时 | 已修复并通过 |
| 7 | offers empty Content entries for 'Image & Highlights' independently of saved optional values | 未按布局能力判断 CTA | 已修复并通过 |
| 8 | offers empty Content entries for 'Website Section Story' independently of saved optional values | 未按布局能力判断 CTA | 已修复并通过 |
| 9 | offers empty Content entries for 'Inset Surface Panel' independently of saved optional values | 未按布局能力判断 CTA | 已修复并通过 |
| 10 | offers empty Content entries for 'Wide Visual — Centered Header' independently of saved optional values | 未按布局能力判断 CTA | 已修复并通过 |
| 11 | offers empty Content entries for 'Split Visual — Surface Panel' independently of saved optional values | 未按布局能力判断 CTA | 已修复并通过 |
| 12 | offers empty Content entries for 'Wide Visual — Centered Media Panel' independently of saved optional values | 未按布局能力判断 CTA | 已修复并通过 |
| 13 | offers empty Content entries for 'Wide Visual — Contained Media Panel' independently of saved optional values | 未按布局能力判断 CTA | 已修复并通过 |
| 14 | offers empty Content entries for 'Wide Visual — Editorial Header' independently of saved optional values | 未按布局能力判断 CTA | 已修复并通过 |
| 15 | offers empty Content entries for 'Website Builder Overview' independently of saved optional values | 未按布局能力判断 CTA | 已修复并通过 |
| 16 | offers empty Content entries for 'Plan Comparison' independently of saved optional values | 测试桩遗漏 pages | 已修复并通过 |
| 17 | offers empty Content entries for 'Team Story Overview' independently of saved optional values | 未按布局能力判断 CTA | 已修复并通过 |
| 18 | exposes renderer-backed repeated fields for 'Plan Comparison' | 测试桩遗漏 pages | 已修复并通过 |
| 19 | exposes renderer-backed repeated fields for 'Corporate Feature Story' | 测试写入非法枚举／忽略条件字段 | 已修复并通过 |
| 20 | exposes renderer-backed repeated fields for 'SaaS Packages' | 测试写入非法枚举／忽略条件字段 | 已修复并通过 |
| 21 | exposes renderer-backed repeated fields for 'Partner Logos' | 原有 Logo 能力声明与渲染不一致 | 已修复并通过 |
| 22 | can add, edit, reorder and delete entries in mounted 'Connected Feature Rows' Content | 固定布局占位与 authored flow 混淆 | 已修复并通过 |
| 23 | can add, edit, reorder and delete entries in mounted 'Image & Highlights' Content | 固定布局占位与 authored flow 混淆 | 已修复并通过 |
| 24 | can add, edit, reorder and delete entries in mounted 'Image Cards — Four Mosaic' Content | 固定布局占位与 authored flow 混淆 | 已修复并通过 |
| 25 | can add, edit, reorder and delete entries in mounted 'Image Cards — Three' Content | 固定布局占位与 authored flow 混淆 | 已修复并通过 |
| 26 | can add, edit, reorder and delete entries in mounted 'Website Illustrated Steps' Content | 条目按钮名称／选择器过时 | 已修复并通过 |
| 27 | can add, edit, reorder and delete entries in mounted 'Business Website Scenarios' Content | 条目按钮名称／选择器过时 | 已修复并通过 |
| 28 | can add, edit, reorder and delete entries in mounted 'Website Section Story' Content | 条目按钮名称／选择器过时 | 已修复并通过 |
| 29 | can add, edit, reorder and delete entries in mounted 'Staggered Focus Cards' Content | 固定布局占位与 authored flow 混淆 | 已修复并通过 |
| 30 | can add, edit, reorder and delete entries in mounted 'Contrast Image Cards' Content | 固定布局占位与 authored flow 混淆 | 已修复并通过 |
| 31 | can add, edit, reorder and delete entries in mounted 'Three Images — Staggered' Content | 固定布局占位与 authored flow 混淆 | 已修复并通过 |
| 32 | can add, edit, reorder and delete entries in mounted 'Website Builder Overview' Content | 条目按钮名称／选择器过时 | 已修复并通过 |
| 33 | can add, edit, reorder and delete entries in mounted 'Plan Comparison' Content | 测试桩遗漏 pages | 已修复并通过 |
| 34 | can add, edit, reorder and delete entries in mounted 'Focused Steps' Content | 条目按钮名称／选择器过时 | 已修复并通过 |
| 35 | can add, edit, reorder and delete entries in mounted 'Scroll Stack Cards' Content | 条目按钮名称／选择器过时 | 已修复并通过 |
| 36 | can add, edit, reorder and delete entries in mounted 'Team Story Overview' Content | 条目按钮名称／选择器过时 | 已修复并通过 |
| 37 | can add, edit, reorder and delete entries in mounted 'Editorial Knowledge Triptych' Content | 条目按钮名称／选择器过时 | 已修复并通过 |
| 38 | can add, edit, reorder and delete entries in mounted 'Image Flow' Content | 原有 Image Flow 条目入口遗漏 | 已修复并通过 |
| 39 | can add, edit, reorder and delete entries in mounted 'Partner Logos' Content | 条目按钮名称／选择器过时 | 已修复并通过 |

## 本次新增家族额外暴露的 19 项

这些失败不在历史 39 条中，主要因为分组 Passport 不能用旧的扁平条目假设构造测试数据。

| # | 测试案例 | 处理 |
|---|---|---|
| 1 | can add, edit, reorder and delete entries in mounted 'Connected Cards' Content | Grouped Passport uses per-group entry fields and slot-owned details; generic fixture lacked ownership, valid enums and matching action labels.；通过 |
| 2 | can add, edit, reorder and delete entries in mounted 'Connected FAQ' Content | Grouped Passport uses per-group entry fields and slot-owned details; generic fixture lacked ownership, valid enums and matching action labels.；通过 |
| 3 | can add, edit, reorder and delete entries in mounted 'Connected Hero' Content | Grouped Passport uses per-group entry fields and slot-owned details; generic fixture lacked ownership, valid enums and matching action labels.；通过 |
| 4 | can add, edit, reorder and delete entries in mounted 'Connected Logos' Content | Grouped Passport uses per-group entry fields and slot-owned details; generic fixture lacked ownership, valid enums and matching action labels.；通过 |
| 5 | can add, edit, reorder and delete entries in mounted 'Connected Metrics' Content | Grouped Passport uses per-group entry fields and slot-owned details; generic fixture lacked ownership, valid enums and matching action labels.；通过 |
| 6 | can add, edit, reorder and delete entries in mounted 'Connected Preview Tabs' Content | Grouped Passport uses per-group entry fields and slot-owned details; generic fixture lacked ownership, valid enums and matching action labels.；通过 |
| 7 | can add, edit, reorder and delete entries in mounted 'Connected Pricing' Content | Grouped Passport uses per-group entry fields and slot-owned details; generic fixture lacked ownership, valid enums and matching action labels.；通过 |
| 8 | can add, edit, reorder and delete entries in mounted 'Connected Process' Content | Grouped Passport uses per-group entry fields and slot-owned details; generic fixture lacked ownership, valid enums and matching action labels.；通过 |
| 9 | can add, edit, reorder and delete entries in mounted 'Connected Sticky Stack' Content | Grouped Passport uses per-group entry fields and slot-owned details; generic fixture lacked ownership, valid enums and matching action labels.；通过 |
| 10 | can add, edit, reorder and delete entries in mounted 'Connected Story' Content | Grouped Passport uses per-group entry fields and slot-owned details; generic fixture lacked ownership, valid enums and matching action labels.；通过 |
| 11 | every registered repeated-item preset declares its editable capabilities | Slot-owned detail entries are editable through dedicated controls and not generic repeated fields.；通过 |
| 12 | exposes renderer-backed repeated fields for 'Connected Hero' | Grouped Passport uses per-group entry fields and slot-owned details; generic fixture lacked ownership, valid enums and matching action labels.；通过 |
| 13 | exposes renderer-backed repeated fields for 'Connected Logos' | Grouped Passport uses per-group entry fields and slot-owned details; generic fixture lacked ownership, valid enums and matching action labels.；通过 |
| 14 | exposes renderer-backed repeated fields for 'Connected Metrics' | Grouped Passport uses per-group entry fields and slot-owned details; generic fixture lacked ownership, valid enums and matching action labels.；通过 |
| 15 | exposes renderer-backed repeated fields for 'Connected Preview Tabs' | Grouped Passport uses per-group entry fields and slot-owned details; generic fixture lacked ownership, valid enums and matching action labels.；通过 |
| 16 | exposes renderer-backed repeated fields for 'Connected Pricing' | Grouped Passport uses per-group entry fields and slot-owned details; generic fixture lacked ownership, valid enums and matching action labels.；通过 |
| 17 | exposes renderer-backed repeated fields for 'Connected Sticky Stack' | Grouped Passport uses per-group entry fields and slot-owned details; generic fixture lacked ownership, valid enums and matching action labels.；通过 |
| 18 | exposes renderer-backed repeated fields for 'Connected Story' | Grouped Passport uses per-group entry fields and slot-owned details; generic fixture lacked ownership, valid enums and matching action labels.；通过 |
| 19 | mounts 'Connected Pricing' with applicable controls and three categories | New pricing card exposed incomplete pages mock.；通过 |

## 本地浏览器证据

保留独立 QA 草稿 340：`Local QA — Shared Section Library 20261116`。本轮只在该验收草稿新增 Image Flow，没有修改其他页面／首页／导航。

- 默认三图、布局模式未设置；新增后四图且 `flow`，排序保留 ID，删除返回三图。
- 修改图片 URL 与 alt，再新增至四图，Preview 自动保存后直接刷新 `/admin/pages/340/preview`：四个 figure、URL 和 alt 均保留。
- 无浏览器错误；手机 document clientWidth=scrollWidth=380px。
- 截图：`output/jw-cms-integration-20261114/section-test-audit-20261116/image-flow-four-items.png`。

## 检查记录与限制

原始 before、after-1、residual-2、最终 626 项结果、逐项分类、173 条数据库报告和本轮源码差异均在同名 output 目录。所有意外失败保留在 checkpoint，未重置历史计数。首次完整运行后剩 6 项，改为仅跑有新证据的子集（29 项通过），随后才跑全套。新增条件定价测试缺少 isVisible 的 TypeScript 错误已补齐，并单独重验两种价格模式；未改应用行为。首次构建因生成能力清单过时而停止，按官方脚本生成且审核只有 logo_cloud 变化后构建通过。

测试通过不代表本仓库所有无关测试套件已运行。此次关闭的是已报告 39 条、相关新增失败和 173 卡片专项回归。其他未完成修改仍保留。未测量的总耗时／token 使用量为 unavailable。
