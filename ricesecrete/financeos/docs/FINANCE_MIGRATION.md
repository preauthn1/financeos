# 金融/会员体系功能迁移文档（参考工程 -> 当前项目）

## 目标
把 `E:/ai/financeos/docs/reference/api`（以及其页面行为 `E:/ai/financeos/docs/admin_test.html`）中的“充值/提现/资金流水/会员与团队/运营中心”等后台能力，迁移到当前 Webman + Vue Element Plus Admin 项目（`E:/ai/financeos`）中。

当前项目已完成基础后台（管理员、角色、权限、文章、字典、通知、日志、上传等）。本文件聚焦“金融业务模块”的迁移落地。

## 1. 参考工程目录 -> 当前项目目录映射
### 1.1 控制器层
- `docs/reference/api/app/api/controller/*.php`
  - 对应当前项目：`app/api/controller/*`
  - 主要承担“公共/用户侧”接口（例如充值地址、提现申请、用户资金流水等）。
- `docs/reference/api/app/admin/controller/*.php`
  - 对应当前项目：`app/admin/controller/*`
  - 主要承担“后台管理”动作（例如充值审核、提现审核、资金流水列表、团队统计、配置维护等）。

### 1.2 服务层与模型层
参考工程中常见：
- `docs/reference/api/app/*/model/*Model.php`
- `docs/reference/api/app/*/services/*Service.php`

当前项目建议统一放置：
- 新业务模型：`app/model/*`（遵循 `XxxModel / Xxx` 命名规则，由现有项目习惯决定）
- 新业务服务：`app/service/*`（遵循 `XxxService`）

### 1.3 集成与扩展（支付/打款/短信/邮箱等）
参考工程中常见：
- `docs/reference/api/extend/pay/*`
- `docs/reference/api/extend/transfer/*`
- `docs/reference/api/extend/sms/*`
- `docs/reference/api/extend/email/*`

当前项目建议在后续模块中按“集成实现”拆分到类似目录，例如：
- `app/integrations/pay/*`
- `app/integrations/transfer/*`
- `app/integrations/sms/*`
- `app/integrations/email/*`

这样做的好处是：业务服务（Service）只关心“调用抽象”，集成实现（pay/transfer）可被替换或并行开发。

## 2. 认证与统一响应对齐
### 2.1 认证
参考工程：
- 使用 JWT，并在 API 层通过 `authorization` / token 解码校验用户。

当前项目：
- 由 `app/middleware/AuthMiddleware.php` 统一校验 `Authorization: Bearer <token>`
- 校验通过后把 `admin_id/admin_roles` 写入 request
- 并进行 API 权限校验（超级管理员跳过）

结论：迁移金融业务时，后台接口直接按当前项目的 `Authorization + Permission slug` 体系落地即可。

### 2.2 统一响应
参考工程：
- `msg(0, data, msg)` 表示成功
- `msg(-1, '', msg)` 或其他 code 表示失败

当前项目：
- 使用 `success(data, msg)`：返回 `{ code: 200, msg: 'success', data }`
- 使用 `fail(code, msg)`：返回 `{ code: <非0>, msg, data: null }`
- 错误码详见 `docs/API.md`

结论：迁移时不要沿用参考工程的 `-1/200/111` 等“历史 code 语义”，而是直接映射到当前项目的 `fail()` 错误码体系；成功一律使用 `success() / code=0`。

## 3. 数据结构来源（DB）
参考工程数据来源：
- `docs/reference/data/init.sql`

该 SQL 包含大量 `qd_*` 表（充值、提现、会员、资金流水、支付平台、运营中心、订单、消息、风控等）。

### 3.1 表前缀策略（按模块分）
你确认了“**表前缀按模块分**”，因此迁移时不保留统一的 `qd_` 前缀，而是按“业务域（模块）”拆分：
- **member_***：会员/代理/团队关系（用户档案、层级树、邀请码等）
- **wallet_***：钱包/资金流水（变更记录、冻结、余额等）
- **pay_***：支付通道/充值/回调（充值订单、支付平台配置、回调记录等）
- **withdraw_***：提现订单与审核流程（提现订单、出款结果、银行卡/地址等）
- **risk_***：风控（羊毛党、IP 风险、黑名单、限制规则等）
- **ops_***：运营中心/统计/日报（运营中心结算、日报表等）
- **sys_***：站内信/通知/配置（如需与现有 Setting/Notification 合并则另行定义）

原则：
1. **每个模块只依赖本模块前缀表**（跨模块通过 Service 调用，而不是随意 join 其它模块表）。
2. **旧表到新表 1:1 映射为主**，必要时再做“拆表/合表/字段改名”的二次优化。
3. 迁移初期优先“跑通业务闭环”，字段改名与范式化在第二阶段做。

建议迁移策略（推荐）：
1. 先从 `admin_test.html` 对应的“首批页面能力”抽取所需表集合（避免一次性导入全部）。
2. 缺哪些表就补哪些表（导入 init.sql 中对应 CREATE TABLE / 或手写迁移脚本）。
3. 为每个表建立当前项目的 Model，并在 Service 中完成核心业务读写。

> 注：本仓库当前未包含完整业务迁移脚本（只有 admin 基础相关的 seeder/permissions），金融业务表建议优先按数据库方案就绪策略导入或补齐迁移。

## 4. 从 `admin_test.html` 抽取的关键模块（用于确定迁移范围）
从 `E:/ai/financeos/docs/admin_test.html` 的菜单与页面块可见（已截取并可继续扩展）：
- 系统管理/配置类：系统设置、充值配置、广告位/运营中心配置、操作日志
- 会员管理：会员列表、代理列表、信誉分管理/记录、提现银行卡设置、用户实名、会员流水、会员充值提现流水、羊毛党、IP监控、站内信
- 金融核心动作：充值（含配置/审核/详情/导出）、提现（含申请、审核、线下转账处理、详情/导出）
- 团队/运营中心：团队规模/团队状态、运营中心相关列表与配置

下一步会把这些页面能力逐一落到“后端接口 + 前端页面”的开发顺序里。

## 5. 开发交付标准（建议）
每个模块至少满足：
1. DB 表字段与 Model 映射正确（能在 Service 中读到关键字段）
2. 后端接口可跑通（success/fail 统一返回）
3. 权限 slug 可配置（菜单/按钮权限能控制）
4. 前端页面可展示列表/详情，并能触发关键动作（审核/提交/导出等）

