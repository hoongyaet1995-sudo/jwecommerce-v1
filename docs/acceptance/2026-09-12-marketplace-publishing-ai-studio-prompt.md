# Marketplace publishing UI handoff

> Superseded by user direction relayed from Side Chat on 2026-09-12: do not send or wait for this expanded design. Reuse ZIP36's existing three-step workflow, automatically populate owned product data, and add only missing mandatory platform fields using existing controls. Keep images, permission checks, request recovery and audit in backend orchestration; use JW prices, zero initial stock and no ongoing sync in Basic WMS. The historical prompt below is reference only, not an implementation requirement or blocker.

Source inspected directly: `jw-ecommerce-dashboard (36).zip`, `src/components/marketplace/PublishProductModal.tsx` (58440 bytes). Current ZIP36 has fixed category/brand/warranty/package fields and simulated results. It does not contain schema-driven category attributes or logistics selection. The local operational adapter currently wraps the earlier ZIP32 publishing component; any new extraction must use the accepted ZIP36 neutral design, not redesign it in Codex.

Backend preparation already available: connected-store reads, category metadata, Shopee attribute/brand/logistics reads, Lazada category/attribute reads, master product source revision, durable task status and lost-receipt recovery. Full server payload construction, image processing and provider acceptance remain incomplete. Do not present these contracts as finished publishing.

## Copy into Google AI Studio

请基于现有最新 ZIP36，只完善 Marketplace 的 `Push JW Master Product to Marketplace` 发布流程。保留整个项目其他页面与 Basic WMS 的布局、样式和行为；复用现有 PublishProductModal 的步骤、组件、neutral 深色主题和交互。不要重新设计整个 Marketplace。

目标：用户可以选择 JW Master 商品和 Shopee/Lazada 店铺，根据平台类目要求补齐信息，审核后发布并查看真实任务状态。此交付由 Google AI Studio 完成前端设计；Laravel API、凭据和实际发布由 Codex 接入。

1. Destination & Product：只支持 Shopee/Lazada；显示连接有效、过期、暂停、测试店铺等明确状态。选 JW 商品后显示其启用 Variant、SKU、数据库价格、图片、重量与尺寸。缺失内容明确提示，不填虚构默认品牌、类目或图片。切换店铺或商品时清除不再适用的审核数据。

2. Category & Attributes：保留类目选择；父类目不可作为最终选择。按传入 schema 动态呈现必填/选填字段，支持文本、数值、单选、多选、允许自定义的选择、日期、富文本、平台图片及单位；未知字段类型明确标记暂不支持并阻止发布。保留字段 label、帮助信息、长度/范围、允许值及校验错误位置。区分商品公共属性与每个 Variant 的销售属性；切换类目时不要携带旧类目属性。Shopee 条件子属性随父选项更新，品牌选项来自相应店铺/类目。Lazada 品牌及属性按 Lazada schema，不沿用 Shopee 品牌接口或固定字典。

3. 图片与物流：展示现有商品/Variant 图片和平台处理状态（未处理、处理中、成功、失败），支持使用已有源组件重试处理；不要暴露 access token。提供 Shopee 可用物流渠道选择及渠道要求的尺寸/运费等字段；禁用渠道显示原因。Lazada 包裹字段跟随其类目要求。不要替用户虚构属性或默认选中物流服务。

4. Pricing & Review：展示每个 SKU 原价、加价规则和最终平台价格；汇总店铺、类目、属性、图片、物流、Variant 数量。Basic WMS 测试发布模式下初始平台库存固定0，持续库存同步关闭并明确说明；不能因为旧 JW stock 有值就填成初始平台库存。未来高级模式可通过 capability 控制，不在此次自动启用。

5. 提交与结果：保留当前异步状态区域，支持审核中、已创建任务、处理中、成功、拒绝、结果待确认。每次操作携带稳定 request key 和 source revision；重复点击禁用。服务端提示商品已变化时要求重新审核。超时保留原提交编号，通过查询恢复原任务，不能自动重复创建商品。成功只有真实任务结果确认后显示；返回 item ID 不等于商品已上架、所有 SKU 已映射或持续同步已开启，分别显示对应结果。刷新或重新打开可以继续查看已保存任务。

6. UI 兼容：1366×768、1024×600 和390×844下弹窗安全边距、固定头部/底部按钮、内部滚动与键盘可达性保持可用。保留ZIP36 neutral深色风格，不引入slate/blue背景，不修改其他页面。

请提供明确的 props/callback 适配入口，例如 metadata、capabilities、loadCategories、loadAttributes、loadBrands、loadLogistics、prepareImages、review、submit、readTask、recoverRequest。前端使用清晰标记的 mock 数据演示控件即可；不要把定时器或随机 ID 模拟的成功混入 operational 模式，也不要在前端存储密钥。不要声称真实平台API已经实现或验收。

请导出完整新 ZIP，注明改动组件及其依赖，让 Codex 只抽取已批准的发布流程及必要依赖。
