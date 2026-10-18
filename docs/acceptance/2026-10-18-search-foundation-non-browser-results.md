# SEO / GEO / AEO P0：非浏览器验收结果

日期：2026-10-18（Asia/Kuala_Lumpur）。基线 HEAD：848d89e1471aca59ce6d3a5a4e0939a82e3329d0；以下证据对应本地未提交工作区，不能当作该基线 commit 已包含这些改动。没有推送或部署。

## 已取得证据

| 项目 | 结果与边界 |
| --- | --- |
| 真实 AI 重做目录 SEO | 46 Product + 18 Category + 11 Collection，共 75 条现有记录已应用；包括草稿/隐藏记录，公开资格仍由原状态决定。沿用后台保存的 OpenAI / gpt-5.6-luna。 |
| CMS AI SEO | 16 个现有页面均写入草稿并保留版本；逐页记录 publishedUnchanged=true，没有自动发布。 |
| 公开目录 HTML | 实际 HTTP 检查 31 个公开记录：title、description、canonical 与 resolver / DB 一致，0 mismatch；未出现 auroracommerce.io。不是排名或业务事实准确率证明。 |
| CMS 首屏 HTML | 无浏览器 JavaScript 的首页 HTTP 响应含真实 H1 “The right container. The right solution.” 和 canonical；没有旧 dummy 域名。 |
| 发现与权限 | 首页 200；现有主页别名 301 到根路径；robots.txt / sitemap.xml 200；account/login 带 noindex,nofollow；受保护后台返回 302 login，只证明访问保护，不代表后台视觉通过。 |
| 自动队列 | 私有 draft Product 134 保存后产生 task 96；search:work --limit=1 实际取走并调用模型。输出被 invalid_or_unsupported_output 拒绝，保留 system fallback；严格 ID/handle/title/status 校验后清理本次私有 QA 记录，任务审计仍在。没有付费盲重试。此样本不能记作 AI 生成成功。 |
| 后端回归 | 67 tests / 491 assertions 通过，覆盖 SearchFoundation、SearchProducer、AiConnectionApi、PageHistory、PageApi、PageDraftApi。之后新增 provider authentication 熔断专项测试：SearchFoundation 9 tests / 57 assertions 通过；与前述重叠，不相加声称 76 个独立测试。 |
| 前端自动化 | 4 files / 14 tests 通过：EntitySearchPanel、IntegrationPages、aiConnections、pageData。 |
| 构建 | npm run build 成功，包括 Admin Vite 与共享 CMS SSR bundle；字体/图片构建期路径提示和大 chunk 警告仍在。 |
| TypeScript | 全项目 tsc --noEmit 仍 exit 2。既有 ProductAdmin / ProductExtraInfoEditor / ProductReviewsAdmin / Dashboard 等错误仍在；不能声称全项目类型检查通过。当前筛选未发现 Search 新文件与已核对编辑器路径错误。 |

后端保护覆盖：旧值审计、源内容过期拒绝、手动锁与版本冲突、AI review 未确认不应用、CMS 草稿与发布隔离、发布前 revision/actor token、搜索独立权限、认证错误暂停该连接版本的搜索任务，重新保存连接版本后恢复。不是全站安全审计。

## AI 质量与用量

- 任务状态：95 applied、1 rejected。95 包括诊断 pilot；当前业务实体为 75 个目录记录 + 16 个 CMS 草稿，不能说成 95 个不同页面。
- 初次 pilot 发现把网站名当作产品品牌、复述内部测试说明；已修改事实字段与提示词约束，保留原审计，之后用修正策略重做当前记录。
- 最后队列 QA 的拒绝原因目前仅存通用校验码，未保存细分违规字段；不能推断具体是格式、长度或事实规则。诊断粒度仍需改善，暂不追加模型调用。
- 已持久化、可统计的 usage：input 32,989 / output 19,635 / total 52,624 tokens。被拒绝任务未保存 usage，因此这不是全部 96 次任务的精确总用量；完整用量和费用均 unavailable。
- 人工抽查 JTG 首页 / Site Storage / Delivery Planning：文案保留需求确认、交付安排与费用待确认的边界，没有将询价自动描述为保证库存或固定交期。此为抽查，不能推出所有自然语言声明都经业务核实。
- 旧模板、示例产品和营销内容仍可能是来源数据。AI 复述来源不等于来源本身真实；仍需业务数据清理。关键词 topics 是内部建议，不生成 meta keywords，也不承诺排名/AI 引用。

## 尚未验收 / 尚未发布

1. 真实浏览器：后台设置保存、Product / Category / Collection 预览与 Advanced、批量选择分页、任务状态更新、CMS 发布确认弹窗、手机与桌面布局、SSR 与 JS 切换视觉一致性。浏览器工具仍返回 Local browser use is disabled in Work settings；不计通过。
2. CMS 新草稿 SEO：现有业务页面本轮未自动发布。专用测试页的 API 与实际 HTTP 发布链已通过（见下方追加记录）；浏览器点击发布确认仍待验收。
3. 持续分钟调度：已注册命令和调度并完成一次真实 search worker；未运行整套 scheduler，避免触发无关库存/Marketplace 工作。Hostinger cron 与中断恢复的真实运行仍属部署验收。
4. 生产验收：尚未 commit / push / deploy；没有生产同 SHA 或 HTTPS 新功能证据。
5. 全项目 TypeScript 既有问题及旧 PagePublicationApi missing-product 策略断言不一致未在本阶段修复。后者未盲重跑，也不在 67 个通过测试内。
6. P1 JSON-LD / 社交卡片 / 多语言 URL / 分析追踪及搜索排名、GEO 引用效果不在本次 P0 接入验收范围。

## 本地证据

- output/search-foundation-real.json：75 个目录实体前后结果与 usage。
- output/search-foundation-cms-real.json：16 个 CMS 草稿与已发布内容未变化检查。
- output/search-foundation-output-audit.json：31 个真实 HTTP 页面及持久化 usage 汇总。
- output/search-foundation-worker-qa.json：实际入队、拒绝、fallback、清理结果。
- output/search-foundation-typecheck-final.txt：全项目现存类型检查失败明细。
- docs/acceptance/2026-10-18-search-foundation-implementation.md：持续问题与执行日志。

当前状态：非浏览器验收已取得以上结果；整体仍 PARTIAL，等待视觉/交互及发布链验收，不申请同步。

## 追加：API 数据联动与 CMS 发布链（2026-10-18）

用户授权继续这两项，明确不使用浏览器、不同步。新增三类 Catalog API create/update 验收通过：保存后真实 afterCommit 产生持久化任务，源内容修改产生不同任务，工作器使用确定性 provider fixture 更新描述，同时保留手动锁定标题，随后通过公开响应核对。此处 provider 为测试替身，不是新增真实付费模型成功样本；之前真实 AI 证据保持独立。

新增 CMS API 完整链通过：受保护 API 修改草稿、记录 checkpoint、获取当前版本发布确认 token、发布、修改、再次发布、恢复旧 revision、重新获取确认并发布。检查 title / description、锁定策略和仅草稿恢复的隔离；没有关闭 search.require_review。

这次发现并修复真实缺口：页面没有 SearchEntry 时，恢复后的 canonical 文档虽保留 SEO 锁定，但 metadata API 报告锁定为 false。现在 CMS 的 titleLocked / descriptionLocked 直接读取文档 seoSettings；新增回归证明恢复后正确。

本地 MySQL 专用页面 41，随机 slug seo-publication-qa-b61babdc68，始终 excluded/noindex，未设为首页；真实 HTTP 五阶段均 200 且标题正确：

1. 发布 Original search verification。
2. 草稿改为 Updated，公开 HTTP 仍为 Original。
3. 发布 Updated，公开 HTTP 变为 Updated。
4. 恢复 Original 到草稿，公开 HTTP 仍为 Updated。
5. 重新确认并发布恢复稿，公开 HTTP 回到 Original。

本地脚本使用业务 service + review token 验证，API 路由/权限由隔离测试覆盖；不把 service 调用声称为浏览器登录测试。全部现有已发布文档 content_hash 不变，SearchTask 数量不变；严格匹配自身 ID/slug 后删除专用 QA 页面。证据 output/search-publication-local-qa.json。

最后相关后端回归（加入 CatalogApiTest）：**86 tests / 681 assertions passed**，16.07 秒。SearchMetadata PHP 语法检查与 scoped git diff --check 通过。仅 PHP / 测试 / 文档变化，未重复前端构建。

余下主要门槛为浏览器操作/视觉与后续经批准的生产验收；业务 CMS 的 16 个新 SEO 草稿没有因此自动发布。本阶段仍无 commit/push/deploy。

## 追加：Chrome 本地浏览器验收（2026-10-18）

范围：用户授权继续 Search 操作与视觉验收、修复本轮 Search 缺陷；保留已有工作区，不 commit/push/deploy，不发布业务 CMS 草稿，不重跑付费生成。基线 HEAD 仍为 848d89e1471aca59ce6d3a5a4e0939a82e3329d0，证据对应未提交工作区。

环境：受支持 cua_repl Chrome extension ID3，profileName Hoong Yaet；工具未提供邮箱，不能独立确认 Gmail。Admin 初次跳转登录页后由用户手动登录，未读取密码/cookie或改权限。桌面1536x639，手机390x844。未使用终端浏览器绕过限制；另用普通HTTP读取初始HTML作为SSR补充证据。

| 验收项 | 结果及证据边界 |
| --- | --- |
| Settings/Search入口 | 真实品牌 JTG Container & Logistics、部署域名 http://127.0.0.1:8500、Search enabled及gpt-5.6-luna可见；Advanced默认折叠，daily_limit100；review/off/auto分别保存重载成功，最终恢复原auto。未改全站noindex或AI连接。 |
| 错误/用量 | 最近任务96 rejected及invalid_or_unsupported_output可见；产品Before/after展示input485/output256/total741。设置表本身仅列记录/状态/模型，不声称有全站usage汇总。最后任务仍96，无新付费任务。 |
| Product/Category/Collection | JTG20ft产品、General Purpose分类、JTG Container Range集合的共享preview、实际URL、AI来源/状态、Advanced开合已验。桌面/390手机截图，Search区域宽约302/294/294，不横溢出。没有保存或覆盖业务实体文案。Product历史对比与usage读取通过。 |
| 批量优化 | 46产品两页，页1 LACK及页2 JTG20ft精确选择2项，High Cube搜索保留跨页选择，工作量显示2exact/up to2requests；取消未queue。最终另验1exact预览，模态打开聚焦搜索，Escape关闭并返回触发按钮。手机弹窗可滚动且Close可达。真实库只有46产品，未声称浏览器达到100；新增隔离API测试证明去重、100预览、101拒绝及零任务/零provider。 |
| CMS SEO/版本/发布 | 新建专用本地page42，标题Search Browser QA20261018，slug blank-771cc1f5，未设首页，双手动锁及excluded。一个现有rich_text布局替换为明确QA文字。原始发布 -> 修改SEO草稿但公开保留Original -> 发布Updated -> 恢复v6草稿但公开保留Updated -> 再发布Original，全程浏览器操作及实际公开标题/noindex核对通过。恢复后checkbox checked属性均true。Cancel/Escape均保持原状态，未确认时Confirm不可用。测试结束归档保留审计；永久删除确认已取消。 |
| CMS AI reload提示 | 只读现有JTG Site Storage已applied草稿，Reload saved draft与说明可见；未发布此业务草稿，未再次生成/应用AI。新的candidate->apply实时模型路径没有重跑，沿用此前API/真实任务证据，不算新增付费生成成功。 |
| Storefront/SSR | 首页/PDP/Category/Collection均有桌面+390截图；title/description/canonical真实且无auroracommerce.io，所测前台无横向溢出。新鲜4HTTP均200，初始HTML的title/H1与浏览器JS后对应文字一致。没有通过禁用JS重跑整套交互，不能推广为所有sections零hydration视觉偏差。 |

本轮发现并修复两处Search样式缺陷，均位于 resources/admin/src/components/search/SearchVisibility.css：

1. 集成后的native dialog计算margin为0，桌面x0/y0；加margin:auto恢复批准独立样例原有的浏览器居中行为。重验桌面x498/y31.96/w540，手机x15/y42.2/w360.4，弹窗滚动及取消可用。
2. 手机发布检查第一行Pass徽标被长标签挤成两行；复用批准样例 .line>.badge 的flex-shrink:0，限定到 .sf-row>.sf-badge。重验徽标宽41.91且Pass单行。

反向去掉这两条声明后，CSS SHA256与本轮修改前B5C7EA3E44B3E2F06C2ED797A137FFB641301840D237FA0C934BBFC37F94EACB完全一致，未重设计或改DOM。另仅新增SearchFoundation batch边界测试；重建public/build-admin及CMS SSR资产会反映当前已有dirty source，不能据此直接发布整个工作区。

最新验证：

- npm run build：Admin+共享SSR exit0，最终Vite13.19s；既有字体/图片路径提示和large chunk警告仍在。
- php artisan test --compact --filter=SearchFoundationTest：11tests/97assertions，8.18s，通过；无真实provider调用。
- npx vitest run src/components/search/EntitySearchPanel.test.tsx：1test通过，1.65s。
- scoped diff --check通过；HEAD未变。前次86tests/681assertions和14frontendtests仍是此前回归证据，未与本轮重叠计数相加。

限制与未通过项：

- Admin Search浏览器console无error/warn；跨页frontTab记录到既有storefront Lucide arrow-up-right未注册warning。未将console整体声称全绿，未扩大Search范围修复该旧图标。
- CMS手机Builder工具栏Add Section局部裁切，位于Search面板之外；未重设计既有Builder shell，完整CMS手机工具栏视觉仍待独立修复。Search设置、SEO区块和发布/批量弹窗本轮通过。
- 全项目tsc既有exit2未修，未重复运行；不声称全项目类型检查通过。生产调度/中断恢复及Hostinger生产验证仍未做。
- 本轮不新增异常provider状态或批量付费调用。100项UI的真实大数据浏览器场景未建立，边界以隔离后端测试及现有UI限制代码为证。
- 现有16业务CMS SEO草稿未被本轮发布；仅专用QA页执行发布链，最终archived。没有commit/push/deploy，不申请同步，不标记生产完成。整体阶段仍PARTIAL，余项按上述限制保留。

截图与JSON目录：C:/Users/User/Documents/Codex/2026-10-18/cua-repl-chrome-hoongyaet1995-gmail-com/outputs/search-browser/。主要文件：settings-desktop.png、settings-mobile.png、product/category/collection-seo-desktop.png及-mobile.png、batch-preview-desktop.png、batch-mobile.png、publication-review-before.png、publication-review-desktop.png、publication-review-mobile.png、cms-restored-locks-desktop.png、cms-locks-mobile.png、cms-ai-reload-mobile.png、qa-published-original.png、qa-restored-published.png、home/pdp/category/collection-desktop.png及-mobile.png、ssr-head-check.json。早期误将1536设置截图命名mobile已用实际390截图覆盖，不计错误图为移动证据。

执行ledger保留工具路径/selector失败历史与3/6/9规则。rg路径通配符错误累计5次命令（Attention，未到6），nestedcwd错误累计2，selector文本不匹配1，猜测文件路径miss1；均非应用测试失败，不能隐去或计为通过。应用样式两项均一次修复后重验通过；新增边界测试首次通过。整体elapsed/token及费用unavailable。

## 追加：统一 Refresh SEO 与剩余视觉缺陷修复（2026-10-18）

用户明确授权：修复CMS手机工具栏裁切/Lucide warning，保持现有设计及dirty changes；移除Product/Category/Collection的Regenerate legacy SEO，Settings/Search只提供一个Refresh SEO入口，不显示Recent optimization tasks，用户无需逐项Optimize。禁止commit/push/deploy。此前批量选择UI验收/设计被本次明确指令替代，后台审计继续保留。

问题与实现：

- CMS工具栏右侧shrink-0且不换行，390px将Add Section挤出屏幕；改为既有flex-wrap/min-w-0/max-w-full规则。More actions原right-0使208px菜单向左出屏，现手机left-0、sm以上仍right-0。只改两个容器的响应式类，无重设计或DOM重组。
- 页脚使用arrow-up-right但resources/js/app.js没有注册ArrowUpRight；补齐现有Lucide导入和注册，保留原图标、尺寸和样式。
- 三类目录移除SearchBatchControl挂载与导入；共享SEO面板移除Optimize/Retry/Apply按钮及相应事件代码，保留只读preview、before/after及手动锁。CMS的Reload saved draft仍可用。
- Search设置移除任务表及/tasks请求，主入口为Refresh SEO，覆盖Product/Page/Collection/Category。保留设置保存按钮及原有设置样式，增加一行简短进度/完成/失败提示，无任务ID或任务列表。

后端契约：

- 新增search_refreshes、search_tasks.refresh_id，已仅在本地应用063000迁移。保留既有审计；迁移回滚先移除索引再删除nullable列，既有search_tasks表保留。
- 服务在事务内按100条分块生成固定范围的持久任务。重复点击复用进行中的刷新；不接管正在运行的自动任务。新一轮可以重新刷新已有AI结果，保留旧值审计。
- 手动锁保持保护，CMS政策来自canonical草稿。AI/Review模式下明确Refresh操作自动应用通过验证的新结果；系统模式只从已保存内容生成，不调用LLM。CMS仍只写草稿，绝不隐式发布旧业务草稿或主页。
- 每日额度、来源版本检查、provider熔断继续生效。超额度任务持久等待，界面显示将自动继续；失败保留旧输出。
- 新增search:work --watch可选开发模式，默认search:work和既有分钟调度保持兼容。本地原无运行中的Search worker；已启动仅Search的隐藏worker，PID55364，日志在本聊天work/search-worker*.log，空闲时Processed0 tasks。未启动全套Laravel scheduler，未触发Inventory/Marketplace任务。此为本地进程，不是生产部署或开机服务。

验收结果：

| 项目 | 证据与边界 |
| --- | --- |
| Settings桌面/390手机 | 真实JTG品牌和localhost；Refresh SEO一个优化入口；四类覆盖数46/18/11/17；Recent tasks表0；手机document宽390，无横向溢出。原auto设置保持。 |
| Product/Category/Collection目录 | 三页真实数据加载后均无Regenerate legacy SEO；产品SEO preview保持真实值，Optimize按钮0。未保存或改业务文案。 |
| CMS工具栏390 | Add Section完整位于x256..374，可打开110区块选择器并取消。菜单按钮区间x79.61..274.01，全部留在390px屏内；旧位置为负x。只读使用已归档QA页42，未改其状态/内容或设主页。 |
| Lucide | 首页和PDP各1个arrow-up-right SVG，未转换i元素0；新页面warn/error日志均[]；页脚箭头可见截图。 |
| 后台刷新链 | 隔离API测试覆盖401/403、四类记录、Review显式刷新自动应用、CMS已发布hash不变、同一active run复用、完成后新run、系统模式无provider、canonical手动锁、任务来源/额度/不接管running、CLI worker实际处理、回滚不删审计表。 |
| 前端点击链 | mounted组件测试实际点击Refresh SEO，验证POST刷新、进行中禁用重复点击、简短进度和不请求/tasks；浏览器验证真实布局/入口。没有在现有MySQL目录上点击全量刷新以再次消耗付费模型，因此不将fixture说成新一轮真实AI生成成功。 |

最新验证（重叠结果不相加）：

- 7类相关后端回归：90tests/736assertions，24.28s，通过。包括SearchFoundation、SearchProducer、AiConnectionApi、PageHistory、PageApi、PageDraftApi、CatalogApi。
- 最后新增回滚专项并修复索引删除顺序后：SearchFoundation15tests/142assertions，10.55s，通过。90项回归在回滚顺序修复前完成；随后变化仅迁移down及回滚测试，不反复重跑无关套件。
- SearchVisibilitySettings/EntitySearchPanel前端2tests，2.40s，通过。
- root npm run build exit0，Vite75s；Admin+共享SSR最后build exit0，16.55s。既有字体/图片路径与large chunk警告仍在；未声称全项目tsc已通过，未重跑既有全局失败。
- scoped tracked git diff --check通过，app.js只有Git提示既有CRLF转换提醒；HEAD仍848d89e1471aca59ce6d3a5a4e0939a82e3329d0。前期Search/CMS及其他Inventory/Fashion改动保留。
- 最后本地只读DB核对：tasks96、refreshes0、queued/running0。没有新增真实模型调用，没有再次发布16个业务CMS SEO草稿。

证据目录：C:/Users/User/Documents/Codex/2026-10-18/cua-repl-chrome-hoongyaet1995-gmail-com/outputs/search-refresh/。

截图：settings-desktop.png、settings-mobile.png、products-desktop.png、categories-desktop.png、collections-desktop.png、cms-toolbar-mobile.png、cms-menu-mobile.png、home-icons-desktop.png、footer-icon-desktop.png。主要截图已对稳定DOM/真实数据重新捕获；初次目录空loading图被覆盖。浏览器最终viewport已reset，临时验收标签已关闭。

执行与限制：首次filter=refresh过宽，误选37tests，30pass/7fail，其中2个为声明的缺失新API初始red、5个为无关Marketplace既有失败；未扩大修复或盲重跑。随后全部用精确套件。新迁移回滚测试的预告red确认SQLite索引需先删，修复后15项通过。一次CDP等待超时在新snapshot确认真实46记录后继续；后续验收通过。原rg通配符历史累计5维持，未再重复。elapsed/token/cost unavailable。

本次两项请求的本地实现与上述范围验收已完成。生产、Hostinger调度/同SHA/HTTPS仍未验收；不申请同步，不标记生产完成。全项目既有TypeScript失败仍是独立限制。没有付费全量刷新正在后台等待，用户之后点击Refresh SEO才会发起本次新流程。

## 追加：Search Light Mode 修复（2026-10-18）

用户指出Search设置缺少Light Mode，指定Shop Settings为参考。根因是SearchVisibility.css只有固定深色规则。只在该共享CSS末尾添加 :root:not(.dark) .sf-ui 配色，使用Shop既有white/neutral/indigo体系；卡片、标题、按钮、选择项、高级输入框、notice、SEO预览、对比和发布dialog均覆盖。无DOM、布局、后端或SEO逻辑变动；原深色规则保持。

真实Chrome证据：修复前html.dark=false但卡片rgb(23,23,25)、文字rgb(238,238,244)，标题在浅底不可读。修复后Light卡片rgb(255,255,255)、文字rgb(23,23,23)、主按钮rgb(79,70,229)，高级input白底/neutral300边框。桌面及390x844手机通过，手机document scrollWidth390。Dark复验仍为原23/23/25卡片及238/238/244文字。只读QA归档页42的共享SEO preview白卡/250灰预览/37,99,235标题；发布review白底黑字，四项检查通过，取消后保留archived，未发布或保存。

npm run build Admin+共享SSR exit0，15.67s；原字体/图片路径和大chunk警告保留。浏览器warn/error[]。仅CSS主题改动，未重复无关后端回归。未点击Refresh SEO、未新增AI调用或业务数据修改。验收后恢复原Dark Mode偏好与默认viewport，临时tab关闭。无commit/push/deploy。

截图：本聊天outputs/search-light-mode/ 下 before-desktop.png、light-desktop.png、light-mobile.png、light-advanced.png、dark-desktop.png、dark-mobile.png、publication-dialog-light.png。SEO preview检查以computed style为证，seo-preview-light.png仅为Inspector上下文，未将未进入可视区的preview截图声称为完整视觉证据。

只读rg命令再次使用PowerShell不支持的路径通配符，累计6 High attention，已向用户报告；后续使用精确路径，没有重跑该错误。此错误未改代码或数据。elapsed/token unavailable。
# SEO 夜间刷新与手动刷新：本地验收通过

已取消每日 AI 任务数量上限、内容保存后即时 AI 更新。目录/PDP/CMS 保存仍维护基础 metadata、缓存、旧 URL 跳转和手动字段策略。AI 由每晚马来西亚时间02:00或手动 Refresh SEO 触发；活动轮次复用，手动锁保护，CMS只更新草稿。

## 实际全站运行

沿用先前点击产生的 run1，不另开全站刷新。总92条，成功91条、锁定跳过1条，失败0、待处理0。

| 类型 | 实际成功 |
| --- | ---: |
| product | 46 |
| category | 18 |
| collection | 11 |
| page | 16 |

全部91条的任务结果、SearchEntry版本递增、source hash、目录SEO字段或CMS草稿字段均一致；手动锁保持。16份CMS草稿版本增加，全部14份已发布文档的hash与版本保持不变。归档QApage42完整锁定且未改变。

44个当前公开页面HTTP200，title、description、canonical均与公开metadata解析结果一致：17 product、8 category、6 collection、13 page。CMS公开页继续使用已发布内容，本次草稿SEO没有被发布。

本轮实际使用input32227/output19784/total52011tokens，费用不可用。没有第二轮生成、没有盲重试。

## 本地调度

Laravel注册search:refresh每日02:00 Asia/Kuala_Lumpur，search:work每分钟处理队列。系统触发使用null actor审计；手动触发使用当前管理员。两种触发共享服务。

Windows本地已注册两个仅Search计划任务：JW Ecommerce - Nightly SEO Refresh（每日02:00）、JW Ecommerce - SEO Queue Worker（每分钟处理最多100条/次，这不是每日上限）。两项均实际手动启动测试且退出码0，夜间任务在活动轮次中正确复用run1；没有等到凌晨进行墙钟验收。临时watch worker已停止。

本地计划以当前Windows登录用户运行；电脑需开机、用户已登录、MySQL等应用依赖可用。生产未部署、未配置本次生产调度。

## 回归与浏览器

相关后端92测试/753断言通过（21.61s）；最终SearchFoundation17/182通过（11.21s，含轮询隔离与旧额度任务恢复，和前述重叠）。前端3测试通过（2.56s），覆盖无请求体POST CSRF与临时轮询失败恢复。最终Admin/SSR构建通过，Vite16.46s；已有字体/图片解析和chunk大小警告保留。全项目tsc此前已有失败，未声称全量通过。

真实浏览器发现并修复进度查询共享限流及残留错误：GET/POST使用独立前缀，轮询成功清除历史错误。最终页面显示92条完成，无残留警告；最终载入后console警告/错误为空。Light/Dark保持，手机390px scrollWidth390，旧每日额度输入及任务列表均未显示。截图completed-light.png与mobile-light.png。

HEAD仍848d89e1471aca59ce6d3a5a4e0939a82e3329d0。保留原有全部未提交改动；没有commit、push、deploy。总工程耗时/token不可用。原目录错误历史保留，用户明确恢复后本轮两次发现路径错误已记录，未再盲重试。
