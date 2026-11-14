# Spacing / Alignment 本地验收

2026-11-13。仅本地；未提交、推送、发布、部署。基线 HEAD ea7ce7a041bf8409cd114fe1fbf02742a94918e1；无关 dirty work 保留。

## 修复范围

- 新增一个布局能力矩阵，由 Section Passport 与正式 Inspector 共用。Spacing / Alignment 不再靠旧 JSON 中“字段存在”来决定是否显示。
- 161 个 starter 中，137 个支持间距；其余24个维持固定/响应式留白，隐藏无效控件。包含最初23个无效布局及真实CSS检查发现的 Split Image Hero。
- 74 个 starter 当前提供实际有效的对齐选项；其余按固定排版或当前标题显示状态隐藏。只支持Left/Center时不提供Right；Shop Navigation关闭标题后只保留卡片实际支持的方向。
- Staggered Focus Cards的CSS固定左对齐，因此不提供无效对齐。打开/关闭标题会更新仅属于标题的控件。
- 增加明确的Use layout alignment；Follow page spacing仅清除该Section的间距覆盖。均不改文案、图片、items或其他样式。
- 边框仍属于独立能力，不因布局不支持Spacing/Alignment而一起消失。

没有修改SectionRenderer、布局CSS、模板初始数据或保存schema；没有迁移旧页面。旧paddingY/textAlignment即使当前不支持，也保留在原数据中；切回支持布局后仍可使用。未触及颜色/按钮等其他审计待处理项。

## 验收证据

| 检查 | 结果 |
|---|---|
| 161个默认渲染hash与修复前基线 | 全部一致，default-baseline.json / renderer-evidence.json |
| 161个实际挂载Inspector点击每个显示的选项 | 全部通过，edit-fixtures.json / verified-all161.log |
| 稀疏section、标题开启/关闭、保留旧字段 | 能力稳定且按状态显示；没有挂载即写回 |
| 真实草稿API保存、数据库重读、客户runtime输入 | 全161通过；隔离SQLite测试库；原始、逐选项、合法旧值、恢复原始共9轮 |
| 实际API重读JSON再送入SectionRenderer | 161×9=1449组一致，reload-rendering.json |
| PHP本轮API测试及CMS契约回归 | 96 tests / 541 assertions passed，php-verified.log |
| 前端主回归 | 7 files / 26 tests passed，verified-all161.log |
| API重读渲染专项 | 1 passed，reload-rendering.log |
| 支持布局→固定布局→支持布局及独立重置 | 1 passed，switch-reset.log |
| 最终前端生产构建 | 通过，build-verified.log；保留既有large chunk warning |
| 全量TypeScript检查 | 未通过，typecheck.log有既有项目错误；本轮新增layout controls文件无诊断，不宣称全量lint通过 |

测试数据库为SQLite memory；starter引用的35个managed media URL原样保留，只在测试库建立对应媒体身份。实际localhost数据库中35个媒体身份均已核实存在。API测试未发布页面。

## 真实浏览器

仅新建3个独立本地QA草稿：75 original、76 compact、77 spacious，每页161个Section；用户页面73、74未改动。75的首节用于实际点击/保存/恢复操作，因此最终该节已恢复布局默认，其他Section保持原始数据。

- 前台76/77均实际渲染161个Section。逐节读取真实computed styles：矩阵中137个Spacing对象均有差异，74个Alignment对象均有差异；遗漏0（browser-all161.json、browser-acceptance.json）。固定布局保留其默认几何。
- 浏览器发现“HTML变量改变但CSS无效果”的两例后，已修正矩阵并重新跑全量测试，而不是把静态差分当作效果。
- 正式Inspector：首节Compact上下32px，Spacious上下80px；Right使标题实际靠右。Preview保存→退出→刷新→重开后，Spacious及Right仍选中。
- Use layout alignment、Follow page spacing分别清除对应覆盖；Builder默认上下48px，独立前台桌面默认64px，差异来自原有响应式断点。重置后独立前台仍渲染161节，标题回到继承方向。
- Market Hero无Spacing/Alignment；Split Image Hero无Spacing；Staggered Focus Cards无Alignment。Timeline仅Left/Center。Nursery标题关闭时无Alignment，开启后出现三向，关闭后再次隐藏；原值未丢失。
- 截图：supported-controls.jpg、fixed-market-hero.jpg。

浏览器全量几何检查针对本次桌面viewport；没有宣称遍历全部屏幕宽度或逐项操作商品、支付、物流、slider所有动态业务状态。原有响应式CSS保持不变。

## 维护与交付

生产改动：layoutControlMatrix.json、layoutControls.ts、SectionLayoutControls.tsx、SectionInspector.tsx、sectionDesignCapabilities.ts。后续新增布局或修改renderer/CSS时，必须同步能力矩阵并跑差分与浏览器检查。不能仅因新增JSON字段就自动显示控件。

逐项结果：all-161-acceptance.csv。实现前后差异按本轮source-before生成phase.diff，避免把已有dirty改动误算为本轮。问题计数与原因沿用../checkpoint.md；已记录初始TDD red、夹具依赖/JSON类型/媒体测试库准备、标题状态及CSS消费者修正。未重置历史计数。总elapsed/token unavailable。
