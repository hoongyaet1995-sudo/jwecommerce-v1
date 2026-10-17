# CMS AI Page Generator — 五屏职责与功能规格

日期：2026-10-17。版本：1.0。范围：已经展示的五屏设计及正式接入必须履行的功能契约。

用户已认可视觉方向，并要求明确记录页面职责。本文件整理设计，不表示真实 AI、数据库保存或发布能力已经实现。下文「必须」描述正式接入要求；Sample 状态见第 10 节。后续接入前必须阅读本文件和对应验收记录，不能只根据截图推断行为。

## 1. 产品目的与边界

让不懂页面结构的商家，通过业务需求、系统 Preset 和已有 CMS Section，生成可继续编辑的页面草稿；用户先确认 AI 理解，再开始设计与图片生成。三个模式共用同一套能力，不能分别发展三套互不兼容的数据或渲染器。

| 模式 | 工作范围 | 保留规则 | 完成结果 |
| --- | --- | --- | --- |
| Create a page | 从 Blank Page 规划完整单页 | 复用品牌、真实资料、媒体和现有目标链接 | 一个新的、可编辑的页面草稿 |
| Improve this page | 基于当前页面做调整 | 默认保留现有 Section、图片、链接与内容；重排、替换、删除必须列明 | 当前页面的候选修改版本 |
| Edit selected section | 只处理所选 Section | 其他 Section、全局设置不变 | 所选 Section 的候选修改版本 |

完整页面指一个 CMS 页面，不隐含自动建立整站、创建商品、改动 Theme 或自动发布。JTG 是示例商家，不是系统硬编码的行业模型。

### 所有模式共同遵守

- Pages CMS 负责页面内容；Website Theme 负责系统页面与全局视觉；Catalog 提供商品、Category、Collection 的真实数据；Orders/CRM 的运营逻辑不由生成器改写。
- 使用已有、实际可渲染和可编辑的 Section 类型、布局和字段。不能输出一个漂亮但 Inspector 无法编辑的独立 HTML 页面。
- Builder、预览和客户页面共用正式 SectionRenderer 及 canonical 校验/保存契约。
- 文案、按钮和内容遵守 Page Width。背景可以 Full width；不能因为背景全宽让正文突破内容宽度。
- 字体必须来自统一字体注册系统；main/sub color 来自现有品牌配置。页面方向卡不应悄悄替换 Header、Footer 或全店配色。
- 系统 Preset 是布局、层级、留白、图片风格的方向，不是必须套用的整张固定模板，也不是虚构业务事实的来源。
- 品牌事实、商品规格、项目记录、FAQ 和用户要求分别标明来源；未知资料列为待确认，不编造价格、覆盖区域、库存、案例、证书或承诺。

## 2. 五屏总览与导航

| 稳定 ID | UI 名称 | 唯一主要职责 | 输入 | 输出 | 主要 CTA |
| --- | --- | --- | --- | --- | --- |
| CG-01 | Start | 选择此次 AI 工作范围 | 当前页面、所选 Section、用户权限 | 工作模式和目标范围 | Continue |
| CG-02 | Your Direction | 引导需求，确认真正想达成的方向 | 用户 Prompt、商家上下文、系统 Preset | 可供讨论的需求摘要 | Prepare my brief |
| CG-03 | Design Brief | 把需求变成可审阅的页面设计计划 | 需求摘要、Section 能力、真实链接目标 | 用户确认的 Brief 版本 | Confirm brief & choose images |
| CG-04 | Images | 确认图片来源、生成位置和生成预算范围 | 已确认 Brief、现有媒体、可用图片模型 | 图片计划与生成请求 | Generate editable draft |
| CG-05 | Refine | 检查草稿，进行有范围、可撤回的修改 | 生成候选版本、原版本、用户修改要求 | 应用到 CMS 的可编辑草稿 | Apply to draft |

五个是阶段，不代表每次都强迫用户重新填五份表。已有页面和已有 Brief 可以预填；单 Section 模式沿用同一顺序，只缩小内容范围。Images 允许选择只用现有媒体，零张新图仍然可以生成页面。

Sample 顶部五个按钮用于自由审阅设计。正式流程必须保留前置条件，不能点击顶部 Images/Refine 就绕过 Brief 确认或凭空取得生成结果。已完成阶段可以返回修改；涉及内容、范围、结构或目标链接的修改使原 Brief 确认失效。仅更改图片计划不必重新写 Brief，除非影响页面结构或用途。

## 3. CG-01 — Start / 入口与范围

### 画面职责

在现有 CMS 编辑器 More actions（三点）菜单中提供 AI Page Generator 入口，打开模式选择弹窗。不是另一个独立 CMS 编辑器。

| 控件 | 功能规格 |
| --- | --- |
| AI Page Generator 菜单项 | 打开入口弹窗，携带当前页面和所选 Section 的真实 ID |
| Create a page 卡片 | 以 Blank Page 作为新草稿起点，不能覆盖当前已发布页面 |
| Improve this page 卡片 | 读取当前编辑器工作副本，明确其与已保存/已发布版本的区别 |
| Edit selected section 卡片 | 显示所选 Section 名称；没有选择时要求先选 Section，不能默认修改整个页面 |
| Continue | 保存模式与范围，进入 CG-02；此按钮本身不调用生成接口 |
| Close / Escape | 关闭弹窗、回到编辑器，不保存或发布任何页面 |

正式入口检查 AI Connect 总开关、CMS Vibe Page Builder 使用授权、可用文字模型及 CMS 编辑权限。未配置时解释缺少什么，并提供有权限的设置入口；图片模型缺失不能阻止只用现有图片的文字/布局工作。密钥始终由服务端管理。

未保存的编辑不能静默丢弃；Improve/Section 模式必须基于明确的工作版本。关闭弹窗后焦点回到入口；键盘焦点限制在已打开弹窗内。

验收：CG-01-A 三种模式携带正确目标；CG-01-B 未选 Section 不越界；CG-01-C 无权限/未配置有清晰状态；CG-01-D 关闭不丢失当前编辑。

## 4. CG-02 — Your Direction / 需求引导

### 画面职责

帮助用户把「我想要一个专业页面」说清楚。AI 可以提出少量针对缺口的问题；不能只收到一句 Prompt 就跳过商量直接生成。

| 区域/控件 | 功能规格 |
| --- | --- |
| 左侧 Context from your store | 展示已获取的商家名称、内容/目录概况、品牌设置与来源；计数由真实数据读取，不能固定为 JTG 17 个商品 |
| 主 Prompt 输入框 | 自由描述业务、受众、页面用途、希望保留/避免的内容；允许中英文要求 |
| Prompt 引导 chips | 提供 Introduce my business / Get more enquiries / Launch a service / Tell our story 等起手方向；存在用户输入时不得无提示覆盖其原文 |
| Page goal 卡片 | 选择主要转化目标：Request a quote、Explore products、Contact our team；决定 CTA 层级与信息顺序，不能因此改变商品 purchase mode |
| Visual direction 卡片 | 选择系统 Preset 方向；Industrial、Clean、Warm 是样例，不把所有行业固定成工业图 |
| Page language | 决定生成内容语言；不等同于更改后台界面语言或自动建立多语言站点 |
| Writing voice | 控制文案语气；不能覆盖业务事实 |
| 右侧 Your direction | 实时归纳目标、语气、语言和生成范围，反映用户当前输入 |
| 针对性补问 | 正式接入补齐：业务/受众/页面范围/重点服务等影响方案的信息缺口，允许用户修改答案或表示未知 |
| Prepare my brief | 调用文字规划能力，准备 CG-03；不在此阶段生成图片 |

问题数量按信息缺口决定，不把需求引导做成重复填写后台资料的固定长问卷。已有信息应预填，并允许纠正；无法确认的业务事实进入 Brief 待确认区。

失败状态：空 Prompt 提示补充方向；AI 连接错误保留全部输入；无法获取目录时明确标注缺少上下文，不能伪装已经读取。

验收：CG-02-A 摘要随输入更新；CG-02-B 引导不覆盖原输入；CG-02-C 补问只针对缺口；CG-02-D 无图片请求；CG-02-E 错误可继续编辑/重试。

## 5. CG-03 — Design Brief / 二次确认

### 画面职责

这是用户与 AI 对设计范围达成一致的确认点。它展示「将做什么」，不把规划当作已经生成的页面。

| 区域/控件 | 功能规格 |
| --- | --- |
| Business / Goal / Direction 摘要 | 展示从 CG-02 归纳的需求；保留返回修改入口 |
| Section 计划列表 | 每项包含顺序、稳定目标 ID、语义名称、Section 类型、支持的 layout preset、内容任务及主要数据来源 |
| 编辑名称 | 更改计划中的 Section 标签，不误当作直接修改已发布页面标题 |
| Reorder | 调整顺序，预览计划与后续生成顺序保持一致 |
| Anything else | 记录必须保留、禁止修改、附加说明等约束；与原 Prompt 一起进入生成上下文 |
| Generation plan | 总结 Section 数量、工作范围、保留内容、图片下一步计划及品牌/Page Width 约束 |
| Link plan | 列出 CTA 与真实目标：Page、Product、Category、Collection、支持入口或锚点；区分已验证目标和待选择目标 |
| Unconfirmed facts | 集中展示未证实的业务资料及处理方式：补充、跳过或保留可编辑占位；不得生成肯定式承诺 |
| 小型页面预览 | 结构示意，不标为已完成成品，不承诺未生成的图片已存在 |
| Confirm brief & choose images | 固定此次确认版本并进入 CG-04；不是发布、不是保存正式页面 |

Improve 模式必须列出保留、修改、添加、移除的 Section；Section 模式只列出被授权目标及受影响字段。任何删改计划必须可审阅。链接无法验证时，应要求选择可用目标或移除该链接，不保存假 URL。

Brief 确认记录应对应需求/结构/目标版本。确认后若回头更改关键要求，必须重新确认。不能用一个永久 true 的 confirmed 开关代表以后所有变更都已同意。

验收：CG-03-A 结构来自真实可用 Section 能力；CG-03-B 排序一致；CG-03-C 未确认不得生成；CG-03-D 关键变更撤销确认；CG-03-E 目标可追溯；CG-03-F 范围之外内容保留。

## 6. CG-04 — Images / 图片计划与生成配置

### 画面职责

用户明确选择哪里用现有图片、哪里允许新生成。文字/布局生成与图片生成分开控制。

| 控件 | 功能规格 |
| --- | --- |
| Use existing media | 新图片数量为 0；关闭模型、质量和生成数量控件；从目录或媒体库选择/保留现有图片 |
| Generate missing images | 优先已有素材，仅为确认的缺图位置生成；不能替换已锁定/要求保留的图 |
| Custom image plan | 显式安排生成位置；数量上限不是凭空增加没有用途的图片 |
| 每个图片位置卡片 | 标明 Section、字段/媒体位置、用途、比例、现有/待生成状态、可编辑 image prompt、是否保留 |
| Product photography 锁定提示 | 默认保留真实目录商品图片；本流程不能悄悄生成新 SKU 图片或改变规格外观 |
| Model | 来自 AI Connect 实际允许且适配此任务的模型；Sample 的 GPT Image 2 / 2.5 Flare / 2.5 Sunburst 是展示选项，不能作为生产可用性保证 |
| Quality | 仅展示实际模型支持的质量等级，并说明映射；不向不支持的模型发送虚构参数 |
| Maximum new images | Sample 上限为 1–6，现有图片模式为 0；正式服务端执行同等上限及位置校验 |
| Reference image | JPG/PNG/WebP，Sample 10 MB 上限；正式接入复用上传校验、访问权限和媒体管理；选文件不自动发送给 AI |
| 费用说明 | 开始前显示文字/图片调用范围及可取得的费用估算；拿不到可靠费用时明确未知，不伪造价格或实际 token 数 |
| Generate editable draft | 确认 Brief、图片计划和调用范围后发起任务；防止连点创建重复付费任务 |

参考图上传到商家媒体库，与在生成时发送给模型是不同动作。正式界面须说明用途。图片结果进入媒体库，关联稳定的 Section 媒体字段；AI 示意图不能冒充真实完工项目、客户案例或现场照片。

生成中显示真实任务阶段（规划、文案、图片、链接校验、草稿组合）；禁止用固定计时器冒充完成。可取消未开始任务，已发出的调用费用不能承诺撤销。文字成功、图片失败时保留候选文字结构，并允许保留现有图/单独重试失败位置；不因重试再次生成所有图片。

验收：CG-04-A 现有素材模式零图片调用；CG-04-B 缺图模式保留已有图；CG-04-C 不超数量及指定位置；CG-04-D 配置缺失明确提示；CG-04-E 错误保留成果且重试不重复收费任务；CG-04-F 文件和模型参数校验。

## 7. CG-05 — Refine / 生成结果与后续微调

### 画面职责

先检查候选页面，再选择应用。AI 与现有 Inspector 应操作同一份 canonical Section 数据，不能出现两份视觉相似但互不联动的页面。

| 区域/控件 | 功能规格 |
| --- | --- |
| 左侧 Section outline | 显示真实生成顺序；选择后高亮对应 Section，并同步右侧修改范围 |
| 中央预览 | 用正式 Renderer 显示候选版本，支持桌面/移动预览；字体、颜色、宽度、图片比例与正式客户页面一致 |
| AI Assistant | 支持通过自然语言修改文案、受支持布局、图片计划与结构；不能用固定答案冒充自由 AI 能力 |
| Change scope | Selected section / Whole page；默认所选范围，扩大范围必须明确，不能跨到 Header、Footer、Catalog 或别的页面 |
| 保留约束 | 「保留图片」「不改链接」等要求进入修改契约，不能只在聊天里答应 |
| Preview AI change | 形成候选修改；不直接覆盖编辑器草稿或已发布页面 |
| Original / Proposed | 展示实际变更字段与视觉结果；整页调整须列明增删/重排/布局/图片/链接变化，不只显示一段聊天解释 |
| Apply this change | 仅把本次提议应用到候选版本，写入可撤回的操作历史 |
| Discard | 丢弃此次提议，保留原候选版本 |
| Undo | 撤回已应用的候选修改，包括文案、布局、媒体关联、顺序与链接；不只是撤回 Hero 标题 |
| Inspector | 使用现有 Content / Design / Advanced 和同一字段能力契约；手动编辑与 AI 预览即时一致 |
| Preview | 隐藏编辑侧栏，检查候选客户视图，不改变保存/发布状态 |
| Apply to draft | 经服务器权限、版本与 canonical 校验后，将候选版本应用到 CMS 草稿并返回既有编辑流程；保留可恢复的旧草稿版本 |

有未处理的提议时，Apply to draft 要求先应用或丢弃，避免用户误以为所有聊天建议都已进入草稿。并发修改引起版本冲突时，显示冲突，不覆盖其他编辑。

新建模式应用到新的草稿；Improve 模式应用到当前页面草稿；Section 模式只更新目标 Section。应用后的页面依旧可以通过普通 CMS 控件编辑、保存、刷新再编辑。

Publish 是现有 CMS 的单独动作，遵守发布校验。生成、Apply this change 和 Apply to draft 都不能自动发布、自动设为首页或同步线上。

验收：CG-05-A AI/Inspector 数据同源；CG-05-B 范围严格；CG-05-C 差异真实；CG-05-D 撤回覆盖实际变化；CG-05-E 三模式正确保存；CG-05-F 刷新可编辑且公开页面不提前改变；CG-05-G 冲突不覆盖。

## 8. 跨屏状态、保存与 AI 对接契约

建议状态链：scope_selected → direction_draft → brief_ready → brief_confirmed → image_plan_ready → generating → candidate_ready → refining → applied_to_draft。failed/cancelled 状态保留最后一个有效成果，不转为完成。

正式数据必须保留以下语义，具体表/字段/API 名称留给实现计划确定：

| 数据 | 责任 |
| --- | --- |
| Session identity | 商家/操作者、模式、页面 ID、选中 Section ID、原页面/工作副本版本 |
| Direction | 原始 Prompt、引导回答、主要目标、语言、语气、Preset、事实来源及待确认项 |
| Brief | Section 与 layout 清单、顺序、保留/修改范围、链接计划、确认版本 |
| Image plan | 每个目标媒体字段、来源、prompt、比例、模型/质量、上限、参考素材及保留约束 |
| Generation job | 幂等标识、状态、成功/失败位置、provider 请求关联、实际可取得的使用量 |
| Candidate revisions | 原版本、候选 Section 数据、提议差异、已应用修改历史、可恢复版本 |
| Draft application | 谁在何时把哪个候选版本应用到哪个草稿，基于哪个原版本 |

服务端复核所有权限、商家归属、Section 白名单、layout/field 能力、链接目标、媒体归属及版本。AI 提供建议，不能绕过既有校验。API key 不进入前端数据、Prompt 日志或页面内容。

AI Connect 的已存在开关为 `enabled` 与 `builder_enabled`，文字/图片模型由既有连接配置管理。开关存在不代表本规格的任务执行已存在。Support Chat 与 CMS 生成器共享连接配置时仍保持用途授权与业务上下文隔离。

用户的 CMS 输入、参考网页/文档属于需求或来源资料，不作为改写系统权限、泄露密钥、执行代码或发布页面的指令。

## 9. 视觉资产与实现前阅读顺序

所有路径相对项目根目录。

| ID | 独立 Sample URL query | 视觉设计稿 | 浏览器截图 |
| --- | --- | --- | --- |
| CG-01 | `?screen=start` | `output/cms-ai-generator-sample/01-entry-concept.png` | 同目录 `01-entry-browser.png` |
| CG-02 | `?screen=direction` | `output/cms-ai-generator-sample/02-direction-concept.png` | 同目录 `02-direction-browser.png` |
| CG-03 | `?screen=brief` | `output/cms-ai-generator-sample/03-brief-concept.png` | 同目录 `03-brief-browser.png` |
| CG-04 | `?screen=images` | `output/cms-ai-generator-sample/04-images-concept.png` | 同目录 `04-images-browser.png` |
| CG-05 | `?screen=refine` | `output/cms-ai-generator-sample/05-refine-concept.png` | 同目录 `05-refine-browser.png` |

Sample：`http://127.0.0.1:8500/page-template-sample/cms-ai-generator.html`。

实现前阅读顺序：本规格 → 五张设计稿及可点击 Sample → `docs/acceptance/2026-10-17-cms-ai-generator-sample.md` → 当前 Section 能力/Inspector/Renderer 与保存契约 → AI Connect 实际配置 → 经审阅的实现计划。

视觉稿负责组合、层级、间距和响应式意图；本规格负责行为边界。视觉图中 AI 意外生成的业务承诺、品牌事实、模型名称或计费数字不能作为后端事实。出现冲突先记录并澄清，不能凭实现方便静默删减页面职责。

## 10. Sample 与正式能力之间的差距

| 能力 | 当前 Sample | 正式接入要求 |
| --- | --- | --- |
| 五屏导航/三种模式 | 可点击，工作范围为示例状态 | 真实页面、权限与范围管理 |
| 需求输入/选择 | 可操作，右侧摘要展示 | 真实上下文、AI 补问、需求归纳 |
| Design Brief | 固定 JTG 计划，可编辑名称和排序 | 基于需求及能力目录的动态计划、目标验证、版本确认 |
| 图片配置 | UI 可操作，上传仅本地预览 | 服务端上传、真实模型/图片任务、媒体关联、使用量管理 |
| 生成进度 | 固定时间 UI 模拟 | 可追踪的真实异步任务 |
| 草稿内容 | 固定演示内容/已有图片 | 真实生成的 canonical Section 数据 |
| AI 微调 | Hero/Services 标题的脚本演示 | 所有受支持字段的范围化修改及真实差异 |
| Inspector | 简化的本地标题编辑 | 现有完整 Content / Design / Advanced 控件 |
| Undo | 演示标题撤回 | 完整候选版本/操作历史 |
| Apply to draft | 本地状态与提示，不保存 | Laravel + MySQL 草稿保存/刷新/恢复 |
| 发布 | 无 | 仍走现有 CMS 手动发布流程 |

完整接入的验收至少覆盖：三模式 × 现有图/AI 图；新建和已发布页面的草稿隔离；Brief 失效；不支持布局/字段；失效链接；生成部分失败；重复提交；恢复/撤回；权限与并发；Builder/save/refresh/public 一致；桌面及手机宽度。每项对应本文件稳定验收 ID，不能用 Sample 截图代替真实闭环证据。

## 11. 本次记录与下一步

本次只固化五屏功能规格，没有更改产品功能、调用 AI、保存商家页面、提交 Git 或部署。正式接入应先把本规格拆成可验证的实施计划；真实开关、任务、媒体与保存结果完成后，才能把第 10 节状态改为已实现。

## 12. 实现版本 1.1：可编辑 Brief 与补问闭环
本节取代第11节的旧 checkpoint 状态。真实实现已接入，验收记录见 docs/acceptance/2026-10-17-cms-ai-generator-implementation.md。

AI 问题契约：id、type、question、help、required、options。type 仅允许 text/select/radio/multiple_select。text 用于自由说明；select 用于单选方向列表；radio 用于并列单选或二元开关；multiple_select 用于可复选答案。选择题至少2个有效选项，答案必须属于选项集合。最多24题，超出明确报错，不静默截断。

CG-03 Answer questions 打开逐题弹窗：Question n of total、前进/后退、必答校验、可选题跳过、答案回顾/编辑、提交。关闭保留答案。提交给真实 LLM 更新 Brief，保留已确认回答；有进一步问题继续该闭环。不会将补问按钮当作返回 Step2 的替代。修改 Brief 或排序会使旧确认失效，需要更新并重新确认。

当前正式功能：五屏布局、三种范围、商家/目录/FAQ服务端上下文、动态 Brief、动态能力选取、真实文案生成、现有图/选定AI图/参考图上传、差异审阅、Apply/Discard/Undo、共享 SectionRenderer/完整 Inspector、现有 CMS 草稿保存。真实图片模型由 AI Connect 验证返回的支持列表决定。

新建能力目录限7类：Hero、Feature Cards、Media Text Split、Rich Text、Product Listing、FAQ Accordion、CTA Strip。AI不创建任意HTML或新的Section类型；改进已有页面保护原ID、链接、媒体及非目标区域。FAQ已有问答保护，AI仅改该Section的介绍字段。生成图片用于允许的编辑图片位置，不替换商品真实照片或Logo。全局品牌/字体/Page Width沿用系统。

实现不等于全部组合已经真实调用验收：实际provider走过新建、改进Brief、补问、整页生成/微调和一张图片；四题型、选中Section边界、重复请求、过期/跨用户和失败输入由自动化检查。参考图路径由mocked API及媒体入库检查，未再消费真实参考图生成。异步持久化worker与跨设备任务恢复暂不属于已实现能力。


## 13. 可靠性与退出版本 1.2
- 全屏右上角 Discard AI draft 和 × 均打开明确选择：Keep editing / Close & keep progress / Discard AI draft。Escape 从确认框返回编辑。放弃清除当前用户/源页面的 AI 工作区，不删除已保存 CMS 页面或媒体；已发生的 provider 消费不退回。
- Workspace 独立 MySQL 表，按用户+源页面隔离，乐观版本检查，7天可恢复；关闭前flush，跨标签加载，服务端有更新则409提示，不静默覆盖。页面源文档改变时阻止旧AI状态继续应用。现有CMS草稿仍由原保存API控制。
- 每次Brief/回答/生成/微调/图片调用前保存 operationId 和待处理状态。服务端记录运行/完成/失败及结果；已完成请求重放不调用provider；运行状态不确定只查询状态，不自动重复生成。无后台worker承诺；PHP进程终止导致的未确认状态需要检查，不能当作已成功或自动收费重试。
- Refine Quality review：空页面/标题、不安全链接、空图片、平面Surface文字对比度、当前预览宽度/文字溢出和加载失败图。阻塞问题需修复；可解释的警告需确认；改文档后重新确认。图片背景对比度、站外URL可达性和所有设备最终效果仍需人工预览。
- Brief每行显示具体Layout/Surface。7类新建类型现在具有19个有界布局选项，AI只可选目录列出的值；不自动开放其它类型。生成活动保留最近30次请求，显示实际模型；仅provider明确返回usage时显示tokens，其余标记Unavailable，不伪造费用。
- Journal Rows补入Add Section；Photo Marquee实际图片编辑在统一图片目标面板，修正测试定位后完整挂载编辑/增加/重排/删除通过。
