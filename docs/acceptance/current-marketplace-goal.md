# Current Marketplace delivery objective

Approved direction: 2026-09-08. Supersedes the earlier ZIP32 and full-takeover-first execution order. After the user cleared the old goal, the revised three-phase objective was successfully created in Codex with active status on 2026-09-08.

持续推进 JW Ecommerce 完整 Shopee / Lazada 接入及多仓 WMS。唯一首开发环境为 C:/xampp/htdocs/jwstudio-ecommerce，使用现有分支和 http://127.0.0.1:8500；以已批准 ZIP36 为视觉来源，不自行改版。

当前优先交付 Phase 1：Marketplace Inventory Console + Basic WMS，在 ecom.jwaistudio.online 作为线上开发／验收版本发布。整批完成库存来源展示、商品与平台 SKU 映射、首次共享池／独立仓选择、实体仓库管理、入库、出库、手工调整、员工／时间／原因流水、基础余额与明确排除未接管订单的可用量、店铺仓库绑定、sandbox 读取和指定测试店铺的受控库存写入、基础异常提示。

Basic WMS 独立启用，不接管 product_variants.stock 或现有订单库存。测试店铺、SKU、仓库、订单明确隔离标记。平台写入默认关闭，测试写入需显式开关、指定测试店铺及服务端身份验证。未知数量不伪装为零，无模拟成功混入真实结果。

Phase 2 保留：已有余额模式转换、订单预留与自动扣减、历史订单接管、平台广播、仓间调拨、多阶段对账和接管激活、自动退货入库。Phase 3 保留：真实商家 OAuth、生产库存写入和完整履约／物流／退货生命周期。后续 TikTok Shop / Shopify 按另行批准路线推进。

复用既有实现与测试证据，以连贯批次完成本地必要验证，再取得同步批准，提交／推送／部署同一 SHA，随后集中线上验收；不逐小功能请求验收。不以 Basic WMS 发布代表完整计划完成。密钥与 token 仅服务端安全存储，.env 不提交。用户已授权 sandbox 登录和测试操作；仅在真实缺失凭据／权限、超出授权范围或发布同步节点请求用户介入。

Execution specification: ../superpowers/specs/2026-09-08-basic-wms-release-design.md
Execution plan: ../superpowers/plans/2026-09-08-basic-wms.md
