# AI Support：统一工具检索架构

日期：2026-10-03。状态：设计提案，尚未实现。范围：重构现有 FAQ、Product、Discount 检索编排；Category/Collection、Order 预留接口，不声称已连接。正式 UI 不变。

## 1. 问题与现状

当前 FaqAutoReplyService 收集所有启用 FAQ、最多20个授权优惠事实，然后调用 SupportKnowledgeRetriever 为商品做单独的 count/search/none 规划。回复上下文因此包含无关政策和优惠。商品规划与其他知识没有统一协议。当前30000字节保护是字节限制，不是准确 token 计量。

商品与优惠沿用真实服务；不能让模型生成 SQL、抓取任意 URL 或直接访问 Laravel/MySQL。已保存 support_faq 配置是权限依据，页面未保存的 Active 不生效。

## 2. 目标流程

客户消息 → 身份/会话/限流检查 → 最小必要脱敏 → 确定性快速路径或一次工具规划 → 后端验证计划及权限 → 最多3次有界查询 → 标准化相关事实 → 本地确定性回答或一次 LLM 回答 → 来源/动作/权限重检 → 保存消息。

默认最多2次 LLM 调用（规划1次、生成1次）；无循环式 agent、无自动多次追问检索。工具执行可以顺序依赖：先查商品，再把真实商品ID交给优惠工具。结果不足时明确未知或请求必要信息。

第一轮传工具名称、用途、参数 schema、最新问题及短会话引用。不会传完整 FAQ、商品目录、券表或订单表。分类目录本身也只传用途，具体分类/系列数据通过工具读取。

## 3. 工具目录与实际权限

| 工具 | 输入 | 真实服务与返回 | 首轮可用 |
|---|---|---|---|
| faq.search | query、可选category | SupportFaqService：启用FAQ、相关政策及必要限制 | 实现后启用 |
| product.search / product.count / product.get | query或真实ID、字段需求 | ProductKnowledgeService：已发布商品、已授权价格/规格/可售状态 | 复用现有 |
| taxonomy.search | query、category或collection | 前台分类/系列服务：可见性、真实路由及关联商品 | 尚未实现，目录不暴露 |
| discount.search / discount.lookup_code | query或code、可选检索所得productIds | DiscountKnowledgeService + Coupon：已授权券及日期/状态/门槛 | 复用并改成查询式 |
| order.get_verified | 已验证会话中的订单选择引用 | 订单归属/字段授权服务，仅返回脱敏字段 | 尚未实现，目录不暴露 |

工具注册表动态过滤：数据源已连接 AND 已保存总开关开启 AND 单项/字段权限允许。直接指定券码也不能绕过关闭的 AI 权限。不存在或未授权的工具计划拒绝执行，不提供被隐藏记录的存在性信息。

模型不得提供customer_id、权限角色、任意SQL、任意URL。订单身份由服务器会话注入；末四位手机不能单独授予订单访问。未验证时本地引导验证，不向 LLM 发订单资料。

## 4. 计划协议

示例：客户问“这款耳机有没有优惠？”

```json
{
  "tools": [
    {"name":"product.search","arguments":{"query":"Aether Studio ANC Wireless"}},
    {"name":"discount.search","arguments":{"query":"eligible official offers"},"depends_on":0}
  ]
}
```

规划验证：工具最多3个；query最多160字符；不接受额外字段；ID必须存在于已授权记录或之前查询结果；依赖必须指向更早步骤；禁止循环和无界列表。解析失败直接降级，不重复调用规划。

## 5. 统一事实协议

```json
{
  "source_id":"discount:12",
  "source_type":"discount",
  "data":{"code":"MERDEKA20","status":"Expired","redeemable_now":false},
  "restrictions":["Cannot currently redeem"],
  "retrieved_at":"server timestamp",
  "version":"source revision",
  "actions":[]
}
```

事实只读、短字段、来源ID稳定。当前 example 不代表真实 voucher ID。价格保留系统货币与服务定义；未知库存明确 unverified。搜索返回部分记录，禁止把部分数量解释为全店数量。

回复返回 answer + source_ids；按钮由 SupportActionResolver 用真实ID生成，模型不生成URL/HTML。订单/优惠按钮仅在实际目标页面与权限契约实现后加入。来源ID校验只能证明引用存在，不能证明每一句话正确；金额、库存、券状态等关键事实优先使用本地模板，其他事实用回归案例验证。

## 6. 检索策略与限制完整性

FAQ不再全量发送：初期对启用FAQ的问题、触发词、答案做统一评分检索，最多3条；同主题配送覆盖范围、排除地区、退换限制作为必带政策一起返回。不得因截断丢失禁止/例外条款。跨语言由规划器规范查询，但不改变事实。低匹配时未知，不以无结果断言商店没有该服务。

商品最多使用已配置1–5条，只读取问题需要且已授权的字段；商品描述视为不可信资料。折扣最多5条，浏览仅推荐有效券；明确券码查询可说明已授权券的过期、停用或限额状态，不推荐兑换。商品优惠资格只有现有结账服务实际支持商品条件并完成验证才可判定；不能假设优惠源已支持全部七种促销。

数据库检索结果不截断JSON。长描述可摘要性裁减，但金额、日期、条件、例外必须完整；预算不够时减少候选记录，保留限制。后续数据规模增长可增加数据库全文/向量检索，不是本轮的依赖。

## 7. 快速路径与 token 预算

精确FAQ、明确真实资源引用、券码状态、全店商品数量可直接查询；适合的结果按用户语言用模板返回，0次 LLM。语义问题：一次短规划 + 一次有界回答。不能仅依靠硬编码问题句子分类。

建议初始预算（工程目标，非已测成本）：规划输出最多250 tokens；规划输入估算<=1200 tokens；最终知识估算<=2000 tokens；最终总输入估算<=3500 tokens；回复沿用管理员150–600输出上限。中文字符与token不是1:1；统一 BudgetService 使用模型适配的计数/保守估算，最终以 provider usage 为准。不能把字节数标为token数。

少量上下文：最近最多3轮的必要客户引用 + 上轮服务端来源ID，不发送全部聊天历史，不把历史AI回答当事实。订单/PII进入规划前必须先脱敏或移除。

缓存：仅缓存工具目录与普通公开政策索引，按知识版本失效。价格、库存、优惠状态执行时重新读取；私有订单结果不进入共享缓存。此阶段不增加回答缓存，避免跨客户或过期政策污染。

## 8. Laravel职责与改造顺序

- KnowledgeToolRegistry：可用工具及参数schema，配置/能力过滤。
- KnowledgePlanner：复用 preset support 模型、解析与验证计划；替代仅商品的旧规划器。
- KnowledgeExecutor：白名单调用、依赖、查询预算、失败分类。
- FAQ/Product/Discount adapters：复用各真实服务，只返回标准事实。
- KnowledgeBudgetService：字段选择、相关性排序、限制完整性、计量。
- GroundedReplyService：人格/语言/不可覆盖安全规则、相关事实与回复生成。
- FaqAutoReplyService：保留会话锁、幂等、人工停止、版本重检与消息落库，委托上述编排。
- SupportActionResolver：保持已有商品卡片；后续资源类型独立扩展。

先新增协议/适配器和隔离测试，再让 FAQ/Product/Discount 走统一路径；删掉旧全量facts拼接及旧商品规划调用，防止出现第三次LLM调用。用短期配置 feature flag 支持切回旧路径，但不能绕过权限/过期券保护。随后本地浏览器验收；Category/Order单独下一阶段。无新业务数据迁移或自动发布优惠。

## 9. 错误与防护

所有用户文本、品牌自定义指令、商品/优惠描述都不能覆盖事实和权限边界。人工已接管、会话关闭或配置版本改变时停止落库。保存前重查引用资源可见性及券状态；关键数据变化则使用最新本地状态回答或明确需要刷新，不输出过期承诺。

查询无依据→unknown fallback；provider不可用→service fallback；身份未验证→验证引导；部分工具失败→只回答成功且完整的部分并说明无法确认其他部分；不把服务错误写成“没有优惠/商品”。按已有日额度与用户请求限流，记录实际调用次数。

## 10. 验收边界

1. 普通配送问题只查FAQ，不读取商品/优惠。
2. 中国配送问法检索到“仅本地配送”的真实限制，不能虚构国际时效。
3. 商品问题只返回相关已发布商品；草稿、关闭字段不可见。
4. 商品+优惠问题最多3个查询、最多2次LLM；不猜资格。
5. 指定MERDEKA20可说明已过期（权限开启且保存时）；泛问优惠不推荐过期券。
6. 关闭单券/总开关后立即不读取；未知或未授权券不泄露。
7. 无依据、非法工具、超预算、provider不可用按对应fallback处理。
8. Order/Taxonomy未接入时不出现在工具目录，不能返回假订单/分类事实。
9. 中英马来与追问使用同流程；UI卡片、链接和价格边界不回退。
10. 记录同一组问题新旧 input/output usage、调用次数、延迟及检索命中；实际对比后才宣称节省。

完成标准：隔离数据库测试、真实配置保存、localhost实际模型回复及真实卡片/开关验收均通过。Mock测试不能替代真实provider验证。当前provider暂时不可用和未保存优惠权限是此前实测阻碍，正式实施时重新核实。只完成本地阶段，GitHub/Hostinger同步仍按批准流程。

## 11. 本次交付

仅写架构方案；没有修改运行流程，没有声称向量RAG已实现。十分钟是本次设计整理目标，不是全部工程实施承诺。精确token费用与完整实施时间 unavailable。

## 12. 后续实施记录（2026-10-03）
核心路由已实现：UnifiedKnowledgeService 动态工具目录/验证/执行；FaqKnowledgeSearch 相关检索与同类限制保留；FaqAutoReplyService 不再全量拼接 FAQ/优惠，也不再调用商品专属规划器。语义查询默认一次规划加一次回答，最多3工具；精确 FAQ 可直接回复，指定授权券码跳过规划。保留现有身份、人格、权限、消息锁与来源按钮。
验证44 PHP测试277断言通过3.96s；真实前台中国配送限制回复及商品RM389卡片通过。日志记录工具名、事实数、事实字节数，无原始客户内容。
尚未实现：精确模型token计数/新旧费用对比、完整PII脱敏、上轮服务端来源引用、Order/Taxonomy工具、新优惠类型正式数据源。预算目前是完整事实16000字节与最终prompt30000字节安全上限，不能当作token测量。FAQ同类政策可能超过3条，为防丢限制而全部保留；超预算失败关闭。优惠已保存权限仍关闭，不擅自修改；已授权优惠查询用隔离测试验证，实际开启后的浏览器验收待保存配置。

## 13. Category / Collection and safe Order phase
User approved deferring Combo / Mix & Match / BOGO until an operational checkout engine exists. Added taxonomy.search using active public categories/collections and active product associations, bounded samples/counts and server-resolved routes. Uses existing Product Search scope.
Added default-off order.lookup with persisted field permissions. Ownership is enforced against the current customer/session and conversation; customer login restricts customer_id, guest lookup requires the existing same-session verified email attempt within15 minutes and restricts to that single order. Raw question supplies order reference; model never supplies identity/authorization. Private order facts are removed before generation; local en/ms/zh response is appended by server. EDD remains unconfirmed; region disclosure only state/country. Planner text masks email/long numeric contact patterns; this does not claim full PII/PDPA compliance.
Verification:50 PHP tests314 assertions passed4.46s;3 UI tests previously passed; latest Admin build passed10.95s. Live taxonomy browser returned actual categories/collections. Authenticated Admin save and customer/OTP browser order acceptance remain pending due login page; isolated tests cover cross-customer, missing/expired verification and no private order data in LLM prompt. No deployment.

## 14. Documents deferred by user approval
Hide the disconnected Documents UI and remove default fabricated cotton-care/courier examples. Preserve existing stored data/types for future Guides & Manuals. No document retrieval tool is exposed now and no example becomes a live FAQ.
Future boundary: published, versioned manuals associated with product/category; explicit AI permission; relevant-section retrieval with source citations; public manuals only for customer chat. Operational product prices/stock/promotion eligibility remain sourced from their services. Implement only after real manual source is needed.
