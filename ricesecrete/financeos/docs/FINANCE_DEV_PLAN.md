# 金融业务模块开发计划（按模块依次落地）

## 0. 迁移到当前项目的实现方式（重要）
当前项目采用：
- 后端：Webman（`config/routes/*.php` + `app/admin/controller` / `app/api/controller`），统一响应 `success/fail`，权限 slug 控制按钮/菜单。
- 前端：Vue 3 + Element Plus，API 封装在 `frontend/src/api`，页面在 `frontend/src/views`，路由/菜单由权限 slug 驱动。

参考工程实现（用于对照）：
- `docs/reference/api/app/api/controller/*`：用户侧接口（例如充值地址、提现申请、用户资金流水）
- `docs/reference/api/app/admin/controller/*`：后台管理接口（例如充值审核、提现审核、列表导出、配置维护）
- `docs/reference/data/init.sql`：数据库表结构 `qd_*`

本开发计划优先按“后台页面能力”拆模块，确保你能逐步用 `admin_test.html` 对照验证。

## 1. 模块顺序（建议从“充值/提现”先跑通）

### 模块 1：金融基础数据（DB 就绪 + 公共模型）
目标：保证充值/提现/资金流水所需的最小表结构可用。
建议最小表（从 `docs/reference/data/init.sql` 的 CREATE TABLE 中抽取）：
> 本项目采用“表前缀按模块分”，下述为**模块前缀后的目标表名**（括号内为参考库 `qd_*` 来源表）。

- 账户/会员（member / withdraw）
  - `me_user`（`qd_home_user`）
  - `me_user_card`（`qd_home_user_card` / `qd_banks` 等按字段确认拆分）
- 支付平台/通道（pay）
  - `pa_platform`（`qd_payment_platform`）
- 充值（pay）
  - `pa_recharge_order`（`qd_recharge`）
- 提现（withdraw）
  - `pa_withdraw_order`（`qd_withdrawals`）
- 资金流水（wallet）
  - `me_user_water`（`qd_money_log` / `qd_home_user_virtual_log` 如需）
- 操作日志（sys）
  - `sy_admin_logs`（`qd_operation_log`），对接现有 `AdminLogModel`

交付清单：
1. 导入缺失表（优先手工导入/或按当前项目迁移体系补齐）
2. 创建 Model（`app/model/*`）并对齐字段类型（decimal/money、timestamp、json 等）

### 模块 2：支付通道与配置（PaymentPlatform + 基础查询）
目标：把 `充值配置/提现配置/支付平台配置` 在后端先做成可查询/可编辑。
交付清单：
1. 后端接口（admin）：
   - 支持列表、启用/禁用、增删改必要字段
2. 后端接口（api）：
   - 给用户侧提供“充值地址/提现通道列表”所需的最小数据

### 模块 3：会员与团队的“基础读”（会员列表 + 钱包概览）
目标：后台能展示会员/代理入口，并能把页面里的“汇总数据”跑通。
交付清单：
1. admin：会员列表、代理列表（支持搜索/分页）
2. admin：用户卡/余额概览（对应页面上的“会员资金流水入口”所需字段）
3. api：如页面需要，提供用户侧的基础钱包/状态数据

### 模块 4：充值模块（user 充值申请 + admin 审核/导出）
目标：让 `admin_test.html` 中“充值配置 + 充值列表 + 充值审核/详情/导出”等流程能跑通。
对照参考工程关键控制器：
- 用户侧：`docs/reference/api/app/api/controller/Recharge.php`
  - `index()`：充值通道列表
  - `getRechargeAddress()`：获取充值地址/创建订单
- 后台侧：`docs/reference/api/app/admin/controller/Recharge.php`
  - `index()`：充值列表（按 type/status 等）
  - `checkStatus()`：充值审核通过/失败
  - `add()`：后台补单
  - `sendMessage()`：发送站内信（如页面需要）
交付清单（至少）：
1. admin 接口：列表查询 + `checkStatus` +（可选）补单与站内信
2. api 接口：获取充值通道 + 获取充值地址/创建订单
3. 前端页面：充值列表/充值配置（先支持展示与审核动作）

### 模块 5：提现模块（user 提现申请 + admin 审核/线下转账处理）
对照参考工程关键控制器：
- 用户侧：`docs/reference/api/app/api/controller/Withdrawals.php`
  - `index()`：提现通道/费用参数
  - `store()`：提交提现申请（扣费/手续费/订单创建）
- 后台侧：`docs/reference/api/app/admin/controller/Withdraw.php`
  - `index()`：提现列表
  - `checkStatus()`：审核通过/失败/限制
  - `offlineTransfer()`：线下转账处理
  - `withdrawDel()`：删除（仅特定状态）
交付清单（至少）：
1. admin 接口：列表查询 + 审核 + 线下转账处理（若页面有）
2. api 接口：通道参数 + 提交提现申请
3. 前端页面：提现列表 + 审核弹窗 + 线下转账入口

### 模块 6：资金流水与会员资金流水页
目标：让用户/后台都能查询“资金流水”，并支持关键筛选条件。
对照参考工程关键控制器：
- 用户侧：`docs/reference/api/app/api/controller/MoneyLog.php`（`index()`：钱包记录）
- 后台侧：`docs/reference/api/app/admin/controller/MoneyLog.php`（资金流水列表/导出）
交付清单：
1. api：用户资金流水（分页 + type/detail_type）
2. admin：资金流水列表/导出
3. 前端页面：会员流水 & 充值提现流水（至少展示字段对齐）

### 模块 7：运营中心/团队奖励（Team Operation Center）
目标：让后台能展示团队规模/状态/运营中心相关列表，并支持结算配置入口。
对照参考工程控制器（可按需逐步拆）：
- 后台侧：`TeamOperationCenter.php` / `TeamOperationCenterIssue.php`
- （若页面包含奖励）`TeamReward.php`
交付清单（至少）：
1. 团队列表/团队统计接口（分页 + 筛选）
2. 配置页面接口（若页面有）

### 模块 8：投资/广告位交易（Finance Product / Investment Product）
目标：如果 `admin_test.html` 里存在“投资产品、订单、匹配/结算”，再进入该模块。
对照参考工程（提示）：
- 用户侧/后台侧分别存在 finance/invest 相关 controller 与 validate/repository
交付清单：
1. 产品与订单列表接口
2. 订单状态变更/结算接口（通常会依赖队列任务/定时任务）

## 2. 每个模块的“后端 + 前端”交付模板
每做完一个模块，建议按固定顺序验收，避免后面返工：
1. 后端：
   - Model 字段对齐（能把关键字段读出来）
   - Service 层完成核心业务（创建订单/扣费/状态流转）
   - Controller + 路由注册完成（`config/routes/admin.php` / `config/routes/api.php`）
   - 权限 slug、按钮/接口权限能通过
2. 前端：
   - `frontend/src/types/*` 增加类型
   - `frontend/src/api/*` 封装接口
   - `frontend/src/views/*` 页面展示列表/详情
   - 路由加入并配置权限指令（`v-permission`）

## 3. 开发从哪里开始（建议你确认）
结合你当前“先跑通充值/提现后台能力”的目标，我建议首个编码模块直接从：
1) 模块 1：金融基础数据（导入必要表 + 建 Model）
2) 模块 2：支付通道与配置
3) 模块 4：充值模块（先把 admin 审核和 api 充值地址跑通）

你确认后，我就按这个顺序开始做代码落地（先从后端 Model/Service/Controller + 路由骨架开始，再补前端页面）。

