# 文字与按钮颜色归属：本地验收

范围：全部 161 个 Section 的文字颜色、按钮颜色、Size、Corners、Hover 和重复／无效入口。本地开发目录 `C:\xampp\htdocs\jwstudio-ecommerce`。未提交、推送或部署，未修改既有业务页面；验收草稿 78–81 均未发布。分支和初始文件快照见 baseline.json / source-before。

## 修复结果

- Typography 负责实际文字角色的颜色；新的显式文字覆盖不会再被部分固定布局 CSS 吞掉。
- Buttons / Action links 负责主要、次要操作的文字及 Hover；只有真实按钮提供背景、Size、Corners。文字链接保持原有链接排版。
- Page Settings 的 Default hover 是页面 Hover 唯一入口。Section 的 Hover 可单独覆盖，单项恢复后重新继承页面值。
- 移除通用且没有实际消费目标的 Accent、按钮／面板混合备用颜色入口；有实际用途的卡片、FAQ 面板颜色保留在 Surface，名称对应实际对象。
- 旧 customColors、旧 typography、内容和媒体保留。新值使用可选元数据，不做旧数据迁移。仅修改 Hover 时保留布局配色，不会意外启用整页调色板。

## 验证证据

| 验收 | 结果 | 证据 |
| --- | --- | --- |
| 161 个真实 Inspector 挂载和编辑事件 | 1,452 次编辑，原内容与旧颜色字段保留 | edit-fixtures.json / final-edit.log |
| 能力清单 | 75 个布局支持按钮几何；96 个支持按钮或操作链接 | all-161-acceptance.csv / consumer-inventory.json |
| 真实 draft API 写入、重新读取及 runtime | 161 × 22 状态；3,542 次比较，0 差异 | api-reloaded.json / reload-rendering.json |
| 默认渲染兼容 | 全 161 个布局：原始、旧页面调色板、旧禁用统一按钮，以及只添加 Hover 的页面样式均通过 | default-baseline.json / preserve-green.log |
| 浏览器前台检查 | 全 161 个 Section，当前真实数据中的 88 个可见操作消费者；颜色／几何无遗漏，Hover 最终态全部匹配 | browser-all161.json / browser-hover-settled.json |
| 手动编辑 → 保存 → 退出 → 刷新 → 重开 | 按钮背景 #234567、文字 #fedcba、圆角 6px；Compact 36px、Large 52px；文字角色与操作链接颜色独立 | browser-acceptance.json / inspector-*.png |
| 单项恢复继承 | 清除局部 Hover 后前台为页面紫色 rgb(138,0,204)，保留 6px 圆角；局部覆盖可重新设置 | browser-acceptance.json |
| 移动前台样本 | 390px 视口，文档宽 390px；按钮颜色、52px Size、6px 圆角保留 | browser-acceptance.json |
| PHP 回归 | 98 tests / 621 assertions 通过 | php-preserve-final.log |
| 最终相关前端回归 | 4 suites / 17 tests 通过；Eyebrow 独立 8 tests 通过 | final-contract-regression.log / eyebrow-final.log |
| 构建 | 成功；保留既有大 chunk 提示 | build-preserve.log |

默认比较仅忽略新增的语义 data 属性；只添加 Hover 的比较另忽略显式样式块，不覆盖或重新生成冻结基线。编辑／保存扫描覆盖全 161 个布局；浏览器 Hover 结论限于当前真实数据中实际渲染的消费者，不表示已执行所有商品、支付或表单业务分支。没有提交业务表单、购买或发布操作。

## 限制与问题记录

完整 `tsc --noEmit` 未通过：仓库既有的模板类型、库目标、Inspector 媒体字段等问题仍存在，详见 typecheck-final.log。本轮新增的颜色能力模块和控件没有独立新增诊断，不能将仓库整体类型检查宣称为通过。

最终 Eyebrow 六状态全布局 DOM 测试第一次超出默认 5 秒限制，没有断言差异；明确提高该次运行 timeout 为 120 秒后 8 项通过（实际总计 21.10 秒）。未改产品逻辑解决测试超时。其他已修复问题及次数见 ../checkpoint.md。整体耗时／token 使用：unavailable。

## 本地查看

- `/admin/pages` → `Local QA — Colors & Buttons`：3 个便于交互检查的代表布局，含页面继承、文字链接和表单按钮。
- `/admin/pages/81/preview`：对应前台渲染样本。
- 草稿 79：全部 161 个 Section 的局部覆盖样本；草稿 80：页面默认按钮样本。

下一步由用户本地查看验收。本记录不授权 GitHub 同步或部署。
