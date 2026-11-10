# 用户模块迁移方案

> 来源：参考项目 `docs/reference/api` + `docs/reference/data/init.sql`  
> 后台页面参考：`docs/admin_test.html`  
> 目标项目：`E:/ai/financeos` (Webman + Vue Element Plus Admin)  
> 模块表前缀：`me_`

---

## 1. 模块概述

用户模块（Member）是金融系统的核心模块，涵盖用户基础信息、钱包余额、等级体系、实名认证、银行卡绑定、资金流水、站内信、IP 风控、信用评分等子功能。

参考项目后台页面结构中，用户相关功能分布在以下菜单节点：

```
会员管理
├── 会员列表        → MemberController
├── 代理列表        → 可合并到会员列表（is_proxy 筛选）
├── 信誉分管理       → MemberCreditScoreController
├── 信誉分记录       → MemberCreditScoreController (子页面)
├── 用户提现银行卡设置 → MemberCardController
├── 用户实名         → MemberRealNameController
├── 会员流水         → MemberWaterController
├── 会员充值提现流水   → MemberWaterController (子页面)
├── 羊毛党           → 可合并到会员列表（风险类型筛选）
├── IP监控           → MemberIpController
└── 站内信           → MemberMessageController

任务管理
└── 个人佣金池       → MemberBrokeragePoolController
```

---

## 2. 表名映射（17 张表）

| 原始表（qd_） | 新表名（me_） | 说明 |
|---|---|---|
| `qd_home_user` | `me_user` | 用户主表 |
| `qd_home_user_card` | `me_user_card` | 银行卡/支付宝绑定 |
| `qd_home_user_address` | `me_user_address` | 收货地址 |
| `qd_home_user_real_name_auth` | `me_user_real_name_auth` | 实名认证 |
| `qd_home_user_water` | `me_user_water` | 资金流水（核心账变表） |
| `qd_home_user_virtual` | `me_user_virtual` | 虚拟数据（团队统计） |
| `qd_home_user_virtual_log` | `me_user_virtual_log` | 虚拟数据变更记录 |
| `qd_home_user_level` | `me_user_level` | 等级变更历史 |
| `qd_home_user_login` | `me_user_login` | 登录记录 |
| `qd_home_user_log` | `me_user_log` | 用户行为日志 |
| `qd_home_user_ip` | `me_user_ip` | 用户 IP 记录 |
| `qd_home_user_message` | `me_user_message` | 站内信 |
| `qd_home_user_credit_score` | `me_user_credit_score` | 信用评分 |
| `qd_home_user_balance` | `me_user_balance` | 余额宝存储记录 |
| `qd_home_user_brokerage_pool` | `me_user_brokerage_pool` | 佣金池 |
| `qd_home_user_ad_buffer_countdown_config` | `me_user_ad_countdown_config` | 广告缓冲倒计时配置 |
| `qd_home_user_excess_ad_ratio_config` | `me_user_ad_ratio_config` | 超额广告位比例配置 |

---

## 3. 发现的问题 & 优化方案

### 3.1 时间字段类型混乱

**问题**：原始表所有时间字段使用 `int`（Unix 时间戳），而非 MySQL 的 `timestamp`/`datetime` 类型。

**优化**：保留 `int unsigned` 类型（兼容 Eloquent 的 `$dateFormat = 'U'` 属性），同时增加 `created_at`/`updated_at` 时间戳列用于 Eloquent 自动维护。

### 3.2 用户主表字段过于臃肿（100+ 字段）

**问题**：`qd_home_user` 表包含 100+ 个字段，将用户基础信息、所有钱包余额、银行信息、层级关系、业务开关、推广配置等全部塞在一张表里。

**优化策略**：

| 字段组 | 优化方案 |
|---|---|
| 钱包余额字段（user_money, recharge_money, brokerage_money 等 12 个） | 保留在 `me_user` 主表中（高频查询，避免 JOIN） |
| 银行信息（bank_code, bank_name, bank_number, bank_kh, bank_address, bank_document_type, document_id） | **移除**，以 `me_user_card` 为准（原表已存在独立的卡表） |
| 区块链地址（bsc_address, recharge_address, withdraw_address, bsc_withdraw_address, usdt 等） | **按需保留**，若当前项目不需要去中心化充值则移除 |
| 推广链接字段（pix, platform, click_id） | **按需保留**，若项目无此需求则移除 |
| leader1~leader7（7 级上级） | 保留，这是 MLM 层级关系核心字段 |
| tree_id | 保留，用于团队树形查询 |
| is_xuni（虚拟用户标记） | 保留 |
| is_demo, cp_id | **移除**，项目特定字段 |
| 任务相关（do_task_status, task_count 等） | 保留到任务模块，用户表仅保留关键标记 |

### 3.3 字符集不一致

**问题**：部分表用 `utf8mb3`，部分用 `utf8mb4`。

**优化**：统一使用 `utf8mb4_unicode_ci`。

### 3.4 字段注释错误

**问题**：多处注释与实际内容不符。例如 `qd_home_user_address.create_time` 注释写的是"头像"。

**优化**：全部修正为正确的字段说明。

### 3.5 外键约束缺失

**问题**：原始表未定义任何外键约束。

**优化**：至少为以下关联加上外键：
- `me_user_card.user_id` → `me_user.id`
- `me_user_water.user_id` → `me_user.id`
- `me_user_level.user_id` → `me_user.id`
- `me_user_real_name_auth.user_id` → `me_user.id`

### 3.6 字段命名规范化

| 原字段 | 新字段 | 原因 |
|---|---|---|
| `user_name` | `username` | 统一命名风格 |
| `nick_name` | `nickname` | 同上 |
| `true_name` | `real_name` | 语义更清晰 |
| `paypwd` | `pay_password` | 拼写完整 |
| `user_money` | `balance` | 语义清晰，基础钱包 |
| `create_time` | `registered_at` | 语义明确（仅在主表） |
| `update_time` | `updated_at` | Eloquent 标准 |
| `delete_time` | `deleted_at` | 软删除标准 |
| `login_times` | `login_count` | 标准命名 |
| `last_login_time` | `last_login_at` | 标准命名 |
| `last_login_ip` | `last_login_ip` | 保留 |

---

## 4. 数据库设计（优化后）

### 4.1 `me_user`（用户主表 — 优化后约 60 字段，精简 40%）

> 核心字段 + 钱包余额字段在用户表，银行信息拆分到卡表。

| 字段 | 类型 | 说明 |
|---|---|---|
| `id` | increments | 主键 |
| `username` | string(100) unique | 登录账号 |
| `nickname` | string(100) | 昵称 |
| `real_name` | string(50) | 真实姓名 |
| `mobile` | string(50) index | 手机号 |
| `phone_code` | string(5) default '' | 手机区号 |
| `email` | string(100) | 邮箱 |
| `password` | string(255) | 登录密码 (bcrypt) |
| `pay_password` | string(255) | 支付密码 (bcrypt) |
| `avatar` | string(255) | 头像 |
| `lang` | string(30) default '' | 语言 |
| **钱包字段** | | |
| `balance` | decimal(20,4) default 0 | 基础钱包（可提现） |
| `recharge_balance` | decimal(20,4) default 0 | 充值钱包 |
| `brokerage_balance` | decimal(20,4) default 0 | 佣金余额 |
| `frozen_balance` | decimal(20,4) default 0 | 冻结金额（提现审核中） |
| `trial_balance` | decimal(20,4) default 0 | 体验金（不可提现） |
| `points` | decimal(20,4) default 0 | 积分 |
| `total_recharge` | decimal(20,4) default 0 | 累计充值 |
| `total_withdraw` | decimal(20,4) default 0 | 累计提现 |
| `total_earnings` | decimal(20,4) default 0 | 累计收益 |
| `total_brokerage` | decimal(20,4) default 0 | 累计佣金 |
| **等级/层级** | | |
| `level_id` | int default 0 | 当前等级ID |
| `leader1` ~ `leader7` | int default 0 | 7级上级ID |
| `tree_id` | string(255) | 团队树路径 |
| `invite_code` | string(30) | 邀请码 |
| `invite_code_status` | tinyint default 1 | 邀请码状态 0禁用 1启用 |
| **状态/标记** | | |
| `status` | tinyint default 1 | 状态：0禁用 1正常 |
| `is_activated` | tinyint default 0 | 是否激活：0否 1是 |
| `is_virtual` | tinyint default 0 | 是否虚拟用户 |
| `is_proxy` | tinyint default 0 | 是否代理 |
| `kyc_status` | tinyint default 0 | KYC实名状态 0未认证 1已认证 |
| `withdraw_status` | tinyint default 1 | 提现开关：0关闭 1开启 |
| **安全** | | |
| `login_count` | int default 0 | 登录次数 |
| `last_login_ip` | string(45) | 最后登录IP |
| `last_login_at` | int unsigned | 最后登录时间 |
| `login_key` | string(255) | 登录Token |
| **业务配置** | | |
| `plan_id` | int default 0 | 计划任务ID |
| `ad_plan_id` | int default 0 | 广告位计划ID |
| `credit_score` | int default 0 | 信用分 |
| `is_credit_score_enabled` | tinyint default 0 | 信用评分开关 |
| `transfer_enabled` | tinyint default 0 | 转账开关 |
| `sign_in_days` | int default 0 | 连续签到天数 |
| `sign_last_at` | int unsigned | 最后签到时间 |
| `lottery_count` | int default 0 | 抽奖次数 |
| `team_count` | int default 0 | 团队总人数 |
| `team_monthly_reset` | tinyint default 0 | 团队数据月重置 |
| `remark` | string(255) | 管理员备注 |
| **时间戳** | | |
| `registered_at` | int unsigned | 注册时间 |
| `created_at` | int unsigned | 创建时间 |
| `updated_at` | int unsigned | 更新时间 |
| `deleted_at` | int unsigned nullable | 软删除时间 |

### 4.2 `me_user_card`（银行卡/支付宝绑定）

| 字段 | 类型 | 说明 |
|---|---|---|
| `id` | increments | 主键 |
| `user_id` | int index | FK → me_user.id |
| `type` | enum('bank','alipay','usdt') | 卡类型 |
| `bank_name` | string(100) | 银行名称 |
| `bank_code` | string(50) | 银行代码 |
| `card_number` | string(50) | 卡号（加密存储） |
| `card_holder` | string(50) | 持卡人姓名 |
| `bank_branch` | string(100) | 开户行 |
| `bank_address` | string(100) | 银行地址 |
| `is_default` | tinyint default 0 | 是否默认 |
| `status` | tinyint default 1 | 状态 |
| `created_at` / `updated_at` | int unsigned | 时间戳 |

### 4.3 `me_user_real_name_auth`（实名认证）

| 字段 | 类型 | 说明 |
|---|---|---|
| `id` | increments | 主键 |
| `user_id` | int | FK → me_user.id |
| `real_name` | string(50) | 真实姓名 |
| `id_card` | string(50) | 身份证号 |
| `id_card_front` | string(255) | 身份证正面照 |
| `id_card_back` | string(255) | 身份证反面照 |
| `hand_hold_photo` | string(255) | 手持身份证照 |
| `document_type` | string(20) default 'id_card' | 证件类型 |
| `status` | tinyint default 0 | 0待审核 1通过 2拒绝 |
| `remark` | string(255) | 审核备注 |
| `auditor_id` | int default 0 | 审核人ID（FK → sy_admins） |
| `audited_at` | int unsigned | 审核时间 |
| `created_at` / `updated_at` / `deleted_at` | int unsigned | 时间戳 |

### 4.4 `me_user_water`（资金流水 — 核心账变表）

| 字段 | 类型 | 说明 |
|---|---|---|
| `id` | bigIncrements | 主键 |
| `user_id` | int index | FK → me_user.id |
| `admin_id` | int default 0 | 操作管理员ID |
| `order_id` | int default 0 | 关联订单ID |
| `title` | string(50) | 账单标题 |
| `type` | tinyint | 钱包类型（1基础 2充值 3佣金 4冻结等） |
| `detail_type` | tinyint nullable | 明细类型 |
| `direction` | tinyint | 1收入 2支出 |
| `amount` | decimal(20,4) | 原始金额 |
| `before_balance` | decimal(20,4) | 变动前余额 |
| `after_balance` | decimal(20,4) | 变动后余额 |
| `rate` | int default 100 | 流水倍率（%） |
| `remark` | string(255) | 备注 |
| `is_virtual` | tinyint default 0 | 是否虚拟用户流水 |
| `created_at` | int unsigned | 创建时间 |

### 4.5 `me_user_level`（等级变更历史）

| 字段 | 类型 | 说明 |
|---|---|---|
| `id` | increments | 主键 |
| `user_id` | int | FK → me_user.id |
| `type` | tinyint default 0 | 0普通VIP 1体验区 2活动专区 |
| `from_level` | tinyint | 原等级 |
| `to_level` | tinyint | 新等级 |
| `amount` | decimal(10,2) | 花费金额 |
| `duration_days` | int | 有效天数 |
| `duration_type` | tinyint | 有效类型 |
| `expired_at` | int unsigned | 到期时间 |
| `created_at` | int unsigned | 创建时间 |

### 4.6 `me_user_login`（登录记录）

| 字段 | 类型 | 说明 |
|---|---|---|
| `id` | increments | 主键 |
| `user_id` | int index | FK → me_user.id |
| `ip` | string(45) | 登录IP |
| `created_at` | int unsigned | 登录时间 |

### 4.7 `me_user_log`（行为日志）

| 字段 | 类型 | 说明 |
|---|---|---|
| `id` | increments | 主键 |
| `user_id` | int index | FK → me_user.id |
| `ip` | string(45) | IP地址 |
| `area` | string(100) | 地区 |
| `type` | tinyint | 行为类型 0登录 1注册 2充值 |
| `device` | string(50) | 设备类型 |
| `uuid` | string(100) | 设备唯一ID |
| `created_at` | int unsigned | 时间 |

### 4.8 `me_user_ip`（IP记录）

| 字段 | 类型 | 说明 |
|---|---|---|
| `id` | increments | 主键 |
| `ip` | string(45) | IP地址 |
| `user_id` | int | FK → me_user.id |
| `created_at` / `updated_at` | int unsigned | 时间戳 |

### 4.9 `me_user_message`（站内信）

| 字段 | 类型 | 说明 |
|---|---|---|
| `id` | increments | 主键 |
| `user_id` | int index | FK → me_user.id |
| `admin_id` | int default 0 | 发送管理员ID（0=系统） |
| `type` | tinyint | 1自定义 2充值到账 3提现到账 |
| `title` | string(200) | 标题 |
| `content` | string(2000) | 内容 |
| `is_read` | tinyint default 0 | 是否已读 |
| `read_at` | int unsigned nullable | 阅读时间 |
| `is_pop` | tinyint default 0 | 是否弹窗 |
| `created_at` / `updated_at` | int unsigned | 时间戳 |

### 4.10 `me_user_credit_score`（信用评分）

| 字段 | 类型 | 说明 |
|---|---|---|
| `id` | increments | 主键 |
| `user_id` | int | FK → me_user.id |
| `total_score` | int default 0 | 总分 |
| `trade_score` | int default 0 | 交易量分 |
| `active_score` | int default 0 | 活跃分 |
| `new_score` | int default 0 | 新增分 |
| `period` | string(6) | 评分月份 YYYYMM |

### 4.11 `me_user_brokerage_pool`（佣金池）

| 字段 | 类型 | 说明 |
|---|---|---|
| `id` | increments | 主键 |
| `user_id` | int index | FK → me_user.id |
| `receivable` | decimal(10,2) default 0 | 可领取 |
| `received` | decimal(10,2) default 0 | 已领取 |
| `created_at` / `updated_at` | int unsigned | 时间戳 |

### 4.12 `me_user_virtual`（虚拟数据/团队统计）

| 字段 | 类型 | 说明 |
|---|---|---|
| `id` | increments | 主键 |
| `user_id` | int unique | FK → me_user.id |
| `balance` | decimal(15,2) | 账户余额（展示用） |
| `total_income` | decimal(15,2) | 总收入 |
| `today_income` | decimal(15,2) | 今日收益 |
| `total_recharge` | decimal(15,2) | 累计充值 |
| `total_withdraw` | decimal(15,2) | 累计提现 |
| `team_total` | int | 团队总人数 |
| `team_today_new` | int | 今日新增 |
| `team_active` | int | 团队活跃人数 |
| `team_total_recharge` | decimal(15,2) | 团队总充值 |
| `level1_count` / `level2_count` / `level3_count` | int | 各层级团队人数 |
| `level1_active` / `level2_active` / `level3_active` | int | 各层级活跃人数 |
| `level1_recharge` / `level2_recharge` / `level3_recharge` | decimal(15,2) | 各层级充值 |
| `level1_brokerage` / `level2_brokerage` / `level3_brokerage` | decimal(15,2) | 各层级佣金 |
| `today_invite_count` | int | 今日直推人数 |
| `week_invite_count` | int | 本周直推人数 |
| `month_invite_count` | int | 本月直推人数 |
| `credit_score` | int | 信用分 |
| `created_at` / `updated_at` | int unsigned | 时间戳 |

> **说明**：虚拟数据表用于展示/造假数据驱动，在前端排行榜等场景使用。若项目不需要"假数据"功能可简化为纯统计表。

### 4.13 `me_user_virtual_log`（虚拟数据变更日志）

| 字段 | 类型 | 说明 |
|---|---|---|
| `id` | bigIncrements | 主键 |
| `user_id` | int index | FK → me_user.id |
| `admin_id` | int default 0 | 操作管理员ID |
| `type` | tinyint | 钱包类型 |
| `direction` | tinyint | 1增加 2减少 |
| `title` | string(50) | 标题 |
| `amount` | decimal(20,8) | 操作金额 |
| `before_balance` | decimal(20,8) | 操作前 |
| `after_balance` | decimal(20,8) | 操作后 |
| `remark` | string(255) | 备注 |
| `created_at` | int unsigned | 创建时间 |

### 4.14 `me_user_balance`（余额宝存储记录）

| 字段 | 类型 | 说明 |
|---|---|---|
| `id` | increments | 主键 |
| `user_id` | int index | FK → me_user.id |
| `amount` | decimal(20,4) | 存储金额 |
| `period` | int | 时间标识 YYYYMMDDHH |
| `settle_date` | int | 结算日期 YYYYMMDD |
| `settle_count` | int default 0 | 结算次数 |
| `status` | tinyint default 1 | 1未提款 2已提款 |
| `created_at` / `updated_at` | int unsigned | 时间戳 |

### 4.15 `me_user_address`（收货地址）

| 字段 | 类型 | 说明 |
|---|---|---|
| `id` | increments | 主键 |
| `user_id` | int | FK → me_user.id |
| `consignee` | string(60) | 收货人 |
| `mobile` | string(60) | 手机号 |
| `region` | string(11) | 地区编码 |
| `address` | string(255) | 详细地址 |
| `zipcode` | string(20) | 邮编 |
| `is_default` | tinyint default 0 | 是否默认 |
| `status` | tinyint default 1 | 状态 |
| `created_at` | int unsigned | 创建时间 |

### 4.16 `me_user_ad_countdown_config`（广告缓冲倒计时配置）

| 字段 | 类型 | 说明 |
|---|---|---|
| `id` | increments | 主键 |
| `user_id` | int unique | FK → me_user.id |
| `no_can_click` | tinyint default 0 | 选"否"时可点击 |
| `yes_can_click` | tinyint default 0 | 选"是"时可点击 |
| `button_duration` | int default 3 | 按钮 loading 时长（秒） |
| `apply_to_subordinates` | tinyint default 0 | 是否对下级生效 |
| `created_at` / `updated_at` | int unsigned | 时间戳 |

### 4.17 `me_user_ad_ratio_config`（超额广告比例配置）

| 字段 | 类型 | 说明 |
|---|---|---|
| `id` | increments | 主键 |
| `user_id` | int index | FK → me_user.id |
| `level_id` | int index | 等级ID |
| `ratio` | json | 比例数组（JSON） |
| `profit_rate` | json nullable | 收益率（JSON） |
| `ad_cycle` | json nullable | 广告周期（JSON） |
| `apply_to_subordinates` | tinyint default 0 | 是否对下级生效 |
| `created_at` / `updated_at` | int unsigned | 时间戳 |

---

## 5. 文件结构

```
app/
├── model/member/
│   ├── UserModel.php                  # me_user 主表
│   ├── UserCardModel.php              # me_user_card
│   ├── UserAddressModel.php          # me_user_address
│   ├── UserRealNameAuthModel.php     # me_user_real_name_auth
│   ├── UserWaterModel.php            # me_user_water（核心账变）
│   ├── UserVirtualModel.php          # me_user_virtual
│   ├── UserVirtualLogModel.php       # me_user_virtual_log
│   ├── UserLevelModel.php            # me_user_level
│   ├── UserLoginModel.php            # me_user_login
│   ├── UserLogModel.php              # me_user_log
│   ├── UserIpModel.php               # me_user_ip
│   ├── UserMessageModel.php          # me_user_message
│   ├── UserCreditScoreModel.php      # me_user_credit_score
│   ├── UserBalanceModel.php          # me_user_balance
│   ├── UserBrokeragePoolModel.php    # me_user_brokerage_pool
│   ├── UserAdCountdownConfigModel.php  # me_user_ad_countdown_config
│   └── UserAdRatioConfigModel.php    # me_user_ad_ratio_config
│
├── admin/controller/
│   ├── MemberController.php          # 用户 CRUD + 列表 + 批量操作 + 钱包调整
│   ├── MemberCardController.php      # 银行卡管理
│   ├── MemberRealNameController.php  # 实名审核
│   ├── MemberWaterController.php     # 资金流水 / 充值提现流水
│   ├── MemberMessageController.php   # 站内信
│   ├── MemberIpController.php        # IP 监控
│   ├── MemberCreditScoreController.php # 信用分管理
│   ├── MemberVirtualController.php   # 虚拟用户/数据管理
│   └── MemberBrokeragePoolController.php # 佣金池
│
├── service/
│   ├── MemberService.php             # 用户核心业务
│   ├── MemberCardService.php
│   ├── MemberWaterService.php        # 资金流水业务
│   ├── MemberMessageService.php
│   ├── MemberIpService.php           # IP 风控
│   └── MemberCreditScoreService.php
│
└── api/controller/
    └── MemberController.php          # 前端公开接口（个人信息、登录等，后续处理）
```

---

## 6. 路由设计

### 6.1 后台路由 (`config/routes/admin.php`)

```php
// 用户管理
Route::get('/members', [MemberController::class, 'index']);
Route::get('/members/{id:\d+}', [MemberController::class, 'show']);
Route::post('/members', [MemberController::class, 'store']);
Route::put('/members/{id:\d+}', [MemberController::class, 'update']);
Route::delete('/members/{id:\d+}', [MemberController::class, 'destroy']);
Route::post('/members/batch', [MemberController::class, 'batch']);
Route::put('/members/{id:\d+}/status', [MemberController::class, 'updateStatus']);
Route::put('/members/{id:\d+}/wallet', [MemberController::class, 'adjustWallet']);
Route::put('/members/{id:\d+}/password', [MemberController::class, 'resetPassword']);
Route::put('/members/{id:\d+}/pay-password', [MemberController::class, 'resetPayPassword']);
Route::put('/members/{id:\d+}/parent', [MemberController::class, 'changeParent']);
Route::get('/members/{id:\d+}/subordinates', [MemberController::class, 'subordinates']);
Route::get('/members/{id:\d+}/team', [MemberController::class, 'teamTree']);

// 银行卡管理
Route::get('/member-cards', [MemberCardController::class, 'index']);
Route::post('/member-cards', [MemberCardController::class, 'store']);
Route::put('/member-cards/{id:\d+}', [MemberCardController::class, 'update']);
Route::delete('/member-cards/{id:\d+}', [MemberCardController::class, 'destroy']);

// 实名审核
Route::get('/member-real-names', [MemberRealNameController::class, 'index']);
Route::post('/member-real-names', [MemberRealNameController::class, 'store']);
Route::put('/member-real-names/{id:\d+}', [MemberRealNameController::class, 'update']);
Route::delete('/member-real-names/{id:\d+}', [MemberRealNameController::class, 'destroy']);
Route::put('/member-real-names/{id:\d+}/audit', [MemberRealNameController::class, 'audit']);

// 资金流水
Route::get('/member-waters', [MemberWaterController::class, 'index']);
Route::post('/member-waters', [MemberWaterController::class, 'manualAdjust']);
Route::get('/member-recharge-withdraw', [MemberWaterController::class, 'rechargeWithdrawFlow']);

// 站内信
Route::get('/member-messages', [MemberMessageController::class, 'index']);
Route::post('/member-messages', [MemberMessageController::class, 'store']);
Route::delete('/member-messages/{id:\d+}', [MemberMessageController::class, 'destroy']);

// IP 监控
Route::get('/member-ips', [MemberIpController::class, 'index']);
Route::get('/member-ips/analysis', [MemberIpController::class, 'analysis']);

// 信用分
Route::get('/member-credit-scores', [MemberCreditScoreController::class, 'index']);
Route::get('/member-credit-scores/logs', [MemberCreditScoreController::class, 'logs']);
Route::post('/member-credit-scores/recalculate', [MemberCreditScoreController::class, 'recalculate']);
Route::post('/member-credit-scores/adjust', [MemberCreditScoreController::class, 'adjust']);

// 虚拟用户
Route::get('/member-virtuals', [MemberVirtualController::class, 'index']);
Route::put('/member-virtuals/{id:\d+}', [MemberVirtualController::class, 'update']);

// 佣金池
Route::get('/member-brokerage-pools', [MemberBrokeragePoolController::class, 'index']);
```

---

## 7. 实施计划

| 阶段 | 内容 | 预估工作量 |
|---|---|---|
| **Phase 1: 数据层** | 创建 17 个 Model（`app/model/member/`）+ 数据库迁移 | 4h |
| **Phase 2: 核心业务** | MemberService + MemberController（列表/增删改查/钱包调整/状态切换） | 6h |
| **Phase 3: 子功能** | 银行卡、实名审核、等级变更、站内信 | 4h |
| **Phase 4: 资金流水** | MemberWaterService + Controller（流水列表/手动调账/充值提现流水） | 4h |
| **Phase 5: 风控/统计** | IP监控、信用分、羊毛党检测、虚拟数据 | 4h |
| **Phase 6: 前端页面** | 会员列表 + 详情 + 各子页面（按 admin_test.html 布局） | 8h |
| **总计** | | **约 30h** |

---

## 8. 与当前项目的对接点

| 当前项目已有 | 对接方式 |
|---|---|
| `sy_admins`（管理员表） | `me_user_water.admin_id`, `me_user_real_name_auth.auditor_id` → `sy_admins.id` |
| `sy_notifications`（系统通知） | `me_user_message` 独立于系统通知，是一套独立的用户消息体系 |
| JWT 认证中间件 | 前端用户接口调用时需区分 `admin` 端和 `api` 端认证 |
| 统一响应格式 | 所有接口使用 `success()` / `fail()` 返回 |

---

## 9. 注意事项

1. **密码加密**：原始项目使用 `md5(md5($pwd) . $salt)` 双重 MD5 加盐方式。迁移时统一改用 `password_hash($password, PASSWORD_BCRYPT)`。
2. **支付密码**：同样改为 bcrypt。
3. **软删除**：原始项目使用 `delete_time`（int）字段。新项目使用 `deleted_at` 配合 Eloquent `SoftDeletes` trait。
4. **资金流水不可删除**：`me_user_water` 是账变核心表，仅支持查询，不支持修改和删除。
5. **并发安全**：钱包余额调整必须在事务中使用悲观锁（`lockForUpdate()`）防止并发超扣。
6. **字段逐步迁移**：`me_user` 表的字段可按需分阶段添加，先实现核心字段（约 60 个），后续按功能增补。
