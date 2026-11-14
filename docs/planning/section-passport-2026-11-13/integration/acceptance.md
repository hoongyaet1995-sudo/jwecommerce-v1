# Section Inspector Passport / Eyebrow 本地接入验收

2026-11-13。开发根目录 C:/xampp/htdocs/jwstudio-ecommerce。没有 commit、push、部署、发布页面或更改 active homepage。

## 扫描范围

- 接入前 READ ONLY：64 PageDocuments、422 Section 实例、105 个已保存 type/preset 组合。
- 161 个当前注册布局，另含已保存但 Picker 外的 editorial-triptych / policy_content default，共163个实际 Inspector。
- Content、Design、Advanced 的输入控件、文字角色、列表、媒体和颜色来源均记录。原始扫描为 pre-mounted-passport-scan.json；接入后为 mounted-passport-scan.json。
- 接入后163个组合：145个 Section Eyebrow owner、3个 Slide owner、15个无标题区例外。161个注册布局本身为143/3/15。实际挂载断言每个 owner 恰好一个内容入口（每 Slide 一个），无标题区零入口。
- 全161渲染检查：有标题区消费统一值、空值零胶囊/零间距；另外覆盖可选 Values 标题开启、Slide切换旧值保持、全库公开文字角色有消费目标。

## 实现边界

- 没有增加 Section type；统一可选 eyebrow 字段、所有权解析、共享胶囊组件和现有 Typography。
- size、weight、color、font family、line height、letter spacing 由原有控件驱动。胶囊圆角、dot、边框、光影统一，随 Surface 调整底色；显式 Typography 颜色仍优先。
- 原本真实 Eyebrow 的 subtitle 兼容读取；22个原本未消费 subtitle 的布局不复活旧隐藏文字。FAQ/panel h2、Chapter labels、caption、数字保持独立语义和原文。
- 清空写入显式空值；新 Slide 初始为空，不能回退到旧 section 标签。旧 Slide 切换前固定各自旧值，保留 badge/subtitle 原数据。
- Eyebrow 一个 Content writer；实际 Eyebrow 的颜色使用 Typography。旧装饰色仍保留为 Accent，不混作标题胶囊。
- 所有 Passport 能力组合共用已有 content/media/items/design 来源；其他 Page Settings / Design 入口重排留待讨论。此次扫描不等于全部非 Eyebrow 控件已完成逐项业务交互验收。

## 自动检查

- 32文件 /111项相关回归通过，包括全161文本角色消费、渲染与清空。
- 补充原始颜色兼容、可选Values、Slider初始化和布局切换检查通过；Hero契约4项通过。
- 实际挂载163个Inspector并断言内容入口数通过。
- PHP CMS单元94项 /485断言通过，SQLite memory环境，无生产库重置。
- npm run build：Admin、客户入口、SSR均完成；只有既有包体积提示。
- 范围内 git diff --check 通过。source-delta.json保存基线对比与新增文件哈希。

## 真实浏览器验收

独立本地草稿73：Local QA — Unified Section Passport。仅新建此草稿；既有页面未改。

1. Content输入文案同时更新Canvas与Outline。
2. Typography字号20、字重700、颜色#6b3db8实际进入DOM样式；颜色失焦提交正常。
3. Light胶囊radius999px、边框、阴影、dot；Contrast恢复默认浅字rgb247,246,242，自定义颜色仍可覆盖。
4. 保存后客户预览与Builder文案/字重/字号一致。关闭编辑器、刷新目录、重新打开草稿后值仍在。
5. 清空后保存，独立服务器预览零pill、零region；恢复验收文案后刷新仍在。
6. 390px实际viewport：document clientWidth380 / scrollWidth380；胶囊222px宽，字号自适应14px，没有横向溢出。临时viewport已还原。

预览：http://127.0.0.1:8500/admin/pages/73/preview
证据：browser-light.jpg、browser-contrast.jpg、browser-preview-desktop.jpg、browser-saved-mobile.jpg、browser-final-inspector.jpg。

## 未通过的全仓检查

整仓TypeScript不是绿色，诊断保存在typecheck.txt（跨catalog/reviews/CMS既有类型和测试fixture等）。首次宽范围CMS测试1069通过/85失败；其中本次影响的字段和角色预期、真实兼容缺陷已修正并通过上述定向回归。其他旧测试仍有73 vs161注册数、6 vs31布局数、缺失pages mock、固定槽位/旧模板预期等问题，未在本次擅自扩展修改。因此不宣称全仓回归或全部Passport业务控件验收通过。

## 独立审查

只读审查发现Values可选标题双胶囊和Slide切换旧值优先级两个P2；均已修复并有回归覆盖。无需改业务操作数据。审查过程和所有失败累计见../checkpoint.md。

全局Page Settings、局部Design其他重复入口仍按用户要求保留，等待下一阶段决定。尚未同步GitHub/Hostinger。
