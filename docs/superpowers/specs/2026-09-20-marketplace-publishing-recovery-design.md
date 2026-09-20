# Marketplace 发布检查与恢复规格

日期：2026-09-20

状态：用户已确认本规格；正在编制实施计划。尚未实施或部署。

## 用户目标与约束

员工必须能从产品上的 `Review existing task` 进入任务详情，知道卡在哪里、该到哪里修正，并重新尝试发布。明确失败允许修改后重试；结果不确定允许有发布权限的员工确认重复风险后发起新的发布尝试，不能永久锁住产品。

使用现有 Google AI Studio 发布弹窗、状态卡、按钮和确认组件的设计。保留历史任务，记录操作责任，不删除旧任务或把未知结果伪装成失败。Marketplace 物流仅使用各平台物流；EasyParcel 不参与。

恢复模型适用于所有平台。此次接入已经可执行的 Shopee、Lazada；TikTok、Shopify 在其真实发布适配器具备能力前不显示可执行重推按钮。

## 已核实的问题

- 生产产品 Baju Kurung Joshua：product 55、store 4、task 5，状态 uncertain，无 external_item_id；日志记录 HTTP 200、provider code 500。保存 SKU 为 BKJS-Pink、BKJS-Blue。
- 当前本地缓存的渠道目录没有匹配这两个 SKU。这不是平台上不存在该商品的证明。
- PublishingStoreStatus 只返回 listed/review，缺少任务身份。Step 1 把对应产品的下一步按钮禁用，没有进入详情的动作。
- 任务表没有持久化失败阶段、错误原因和恢复建议。任务详情只提供状态和 ID，错误画面使用通用文案，甚至包含与实际错误无关的品牌修改建议。
- 浏览器请求恢复会重新返回旧的 uncertain task；仅解锁 UI 或清理 localStorage 不能建立完整恢复流程。
- Lazada reconciliation 同步遍历目录及详情，大目录可能超时；它也不能确认已有 external_item_id 的部分成功任务。
- 成功但 mapping 未完成会继续轮询，没有明确恢复终点。Shopee 部分 variants 失败缺少完整恢复路径。

## 员工入口与信息

1. Step 1 产品标签改为可操作的 `View publishing task`，页脚同时提供相同入口。点击只查询任务，绝不自动 dispatch/create。
2. Product & SKU Mapping 提供发布任务入口，按店铺、产品和状态查看历史；刷新、重新登录及换设备后仍可打开。
3. 详情展示产品/店铺、任务号、更新时间、状态、失败阶段、可公开的错误信息、已确认平台商品 ID、建议下一步，以及旧/新任务关联。
4. 已有未知原因的历史任务明确显示“历史任务未保存详细原因”，不编造原因。Task 5 的安全日志信息可以在审计记录中引用，不能据此把任务标成 rejected。
5. 检查结果不能只依赖 toast：阻挡信息保留在详情或对应字段旁，可返回修正页面。重新进入仍能看到服务器保存的任务结果。

## 阻挡点与恢复矩阵

| 情况 | 可操作下一步 |
| --- | --- |
| 会话过期、权限不足 | 重新登录或联系有发布权限的管理员，回来后恢复原任务详情 |
| 店铺断开、Token 过期、测试授权过期 | 打开 Connect Store 的该店铺，Renew Test Access 或重新 OAuth；返回任务重新检查 |
| 平台发布能力关闭、应用权限/店铺身份不符 | 显示具体配置问题与管理员入口；风险确认不能越过身份和权限检查 |
| 主商品/SKU/图片/价格或分类信息缺失、格式无效 | 指明字段和 SKU，返回对应审查步骤或 JW 商品编辑页 |
| 审查后商品、图片、分类要求或仓库资料变化 | 重新加载当前资料并审查；保留仍匹配当前 schema 的员工输入 |
| 图片上传、平台图片迁移或 schema 查询失败 | 显示失败阶段，允许重试该步骤，不能宣称已创建 listing |
| queued | 查看排队状态，明确点击 Resume Publishing 才执行保存任务；过期审查需重新审查 |
| processing | 查看进度；超过现有中断阈值由服务器判为 uncertain，不能直接并行启动第二次 create |
| rejected 且没有平台 ID | 显示原因，Edit & Retry 开始新的三步审查 |
| uncertain 且没有平台 ID | 推荐 Check Marketplace Result；同时提供确认风险后的 Start New Publish |
| 已有平台 ID、variants/Size Chart/结果保存未完成 | 展示已知 ID 和具体未完成阶段；先核对已有商品，提供对应受支持的恢复或 Seller Center 操作；可确认风险发起新尝试 |
| succeeded、mapping 未完成 | 查看已有商品、重新读取该 listing 并修复映射；不重新 create |
| 已有 listing/mapping | 查看现有映射；失败任务的强制重推不自动覆盖既有映射 |
| 查询失败/网络超时/请求回执丢失 | 保留 task/request 身份，重新查询；明确区分“查不到状态”与“平台明确拒绝” |

平台拒绝的错误必须结合实际返回分类，不能统一建议修改品牌或标题。未知错误提供任务号、检查店铺、人工检查平台及管理员排查路径。

## 重新发布与重复风险确认

普通 corrected retry 用于明确 rejected 且没有平台 ID。uncertain 或部分创建的人工 override 显示：“之前的发布可能已在平台创建商品。再次发布可能造成重复商品或 SKU 冲突。”

操作员确认已人工检查 Seller Center、理解重复风险，填写重推原因后，进入新的完整审查。使用现有发布权限；无现成 Super Admin 独立权限模型时不额外发明一个仅 UI 生效的角色限制。

确认不能绕过店铺身份、授权、平台能力、商品数据验证或在途 processing 锁。确认不会立即发送 create；最后的 Publish 才提交新的审查。

新任务保留 previous_task_id 与独立 request_key；记录操作者、确认文案版本、原因、时间及被覆盖风险的旧任务集合。旧任务继续保持真实状态和平台 ID。数据库事务锁住同一店铺/产品并重新检查阻挡集合；状态变化则要求重新查看，不接受过期确认。重复点击、重试网络请求或多窗口操作仅建立一个新任务。

一次 override 只对这次审查有效，不永久解锁。新任务成为当前跟进任务，历史链仍可查看。浏览器恢复逻辑必须识别新的请求身份，不能又把员工送回旧任务。

已有 variant mapping 不自动转移到强制新建的商品。遇到映射冲突显示已创建的新平台 ID 和待处理冲突，交由现有 mapping 工作流人工处理。

## 服务端设计

- 增加任务诊断字段：stage、error_code、安全用户文案、安全 provider diagnostics、failed_at，以及重推关联和风险确认审计记录。
- 定义统一 task review 数据：status、creation certainty、mapping 状态、blockers、allowed actions。所有动作由服务端依据当前状态计算，前端不得单凭按钮或 localStorage 绕过。
- 保留现有 products 状态兼容接口，增补任务摘要或独立 review endpoint，避免破坏已有消费者。
- 将发布前检查、平台调用、部分成功及结果持久化各阶段分别记录。敏感 Token、签名、原始 request/payload 不进入员工错误详情。
- 新增的“创建已确认、后续步骤未完成”状态描述不得仅因 catalog SKU 匹配就宣称 Size Chart/variants 全部成功；恢复每个阶段需独立证据。
- 核对接口只读取平台商品。优先已知 item ID 和平台已支持的 SKU 查询；如需要分页扫描，保存进度并在后台执行，详情可关闭后重新打开。没有查询能力时提供明确人工核对步骤，不伪造自动恢复支持。
- 店铺权限、操作者权限、CSRF、同店同产品约束、竞态和幂等在每个恢复写操作中重新校验。
- 已有父商品时不会重新执行原任务的 create。平台不支持可靠补全时给出 Seller Center 修正动作和人工风险重推入口。

## 实现范围与验证

主要涉及 PublishingStoreStatus、PublishingTasks、MarketplacePublishingController、MarketplacePublishingDispatchService、LazadaPublishingReconciliation、任务数据适配器、publishingReviewSession 和现有 PublishProductModal。单独服务负责诊断/动作策略，避免在大弹窗中堆叠规则。

验收必须覆盖：

1. Task 5 类历史 uncertain 任务可从产品和任务列表进入；原因未知时如实说明。
2. 打开详情、轮询、刷新浏览器都不调用 product create。
3. 明确 rejected 可修正后重试；新旧任务可追溯。
4. uncertain 风险重推必须显式确认、重新审查，最终仅创建一个新任务。
5. 双击、两个标签页、两个员工并发和丢失响应不会重复启动同一新尝试。
6. Token 过期有正确店铺入口；更新后可返回恢复操作。
7. 已有平台 ID 的任务不被误判成未发布；部分 variants/Size Chart/映射有各自下一步。
8. 大目录核对可查看进度并恢复；不能把缓存未命中当成平台不存在。
9. 强制重推不偷偷覆盖原有映射、不启用库存同步、不连接 EasyParcel。
10. 真实 Shopee/Lazada API fixture、隔离数据库测试与 localhost 浏览器验收；不使用业务数据库执行破坏性测试。
11. 使用已有 Google AI Studio 组件做 UI 对照；不带入 UI States Spec 等开发工具。

## 交付顺序

本文审阅后形成实施计划。在 C:\xampp\htdocs\jwstudio-ecommerce 完成实现、测试、构建与浏览器验证，再进行获准的 GitHub/Hostinger 同 SHA 发布。上线验证可以查询并打开真实任务；不代替用户执行最终商品发布或风险重推。
