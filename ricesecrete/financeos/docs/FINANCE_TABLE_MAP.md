# 金融核心表映射（qd_* -> 按模块前缀的新表）

本文件用于把参考库 `docs/reference/data/init.sql` 中的 `qd_*` 表，映射到当前项目**按模块分表前缀**的新表名。

> 说明：迁移以 **1:1 字段迁移** 为主；实际表名以 `app/model` 中 `protected $table` 为准。  
> 完整前缀规范见 [MODEL_SCHEMA_STANDARD.md](./MODEL_SCHEMA_STANDARD.md)、[DATABASE.md](./DATABASE.md)。

## 1) member 模块（`me_`）

### `me_user`（来源：`qd_home_user`）
用途：会员档案 + 钱包聚合字段 + 团队树关系（leader/tree）。

关键字段（节选）：
- **身份与账号**：`id`, `user_name`, `mobile`, `phone_code`, `password`, `paypwd`, `status`, `is_demo`
- **钱包汇总**：`user_money`, `frozen_money`, `recharge_money`, `brokerage_money`, `earnings_money`, `withdrawal_amount`, `water_money`
- **等级与任务**：`level_id`, `ad_plan_id`, `task_count`, `do_task_status`
- **团队关系**：`leader1..leader7`, `tree_id`, `team_count`, `is_proxy`, `is_total_proxy`
- **提现开关与限制**：`withdraw_status`, `withdraw_rec_count`, `withdraw_rec_recharge`, `withdraw_max_usdt`
- **链上/地址**：`recharge_address`, `withdraw_address`, `bsc_address` 等

索引（建议至少保留）：
- `UNIQUE idx_user_name (user_name)`
- `idx_mobile (mobile)`
- `idx_leader1 (leader1)`
- `idx_tree_id (tree_id)`

## 2) withdraw 模块（`me_user_card` / `pa_`）

### `me_user_card`（来源：`qd_home_user_card`）
用途：用户提现账号/银行卡/支付宝等绑定信息。

关键字段（节选）：
- `id`, `user_id`, `type`（bank/alipay）, `bank_id`, `bank_name`, `true_name`, `bank_number`, `bank_kh`, `bank_address`
- `create_time`, `update_time`

> 参考库里 `qd_home_user` 也存了 `bank_*` 字段；迁移时以 `me_user_card` 为准。

### `pa_withdraw_order`（来源：`qd_withdrawals`）
用途：提现申请与审核/出款结果。

关键字段（节选）：
- 订单：`id`, `order_id`, `user_id`, `is_xuni`, `status`, `create_time`, `check_time`, `pay_time`
- 金额：`original_money`, `withdraw_money`（实际到账）, `convert_money`, `exchange_rate`, `handling_fee`, `tx_fee`
- 收款信息：`true_name`, `bank_name`, `bank_code`, `bank_number`, `withdrawal_address`, `document_type`, `document_id`
- 通道与结果：`type`, `channel`, `trade_no`, `result`, `coin`, `from_address`, `ip`, `admin_id`

索引（建议至少保留）：
- `idx_status (status)`
- `idx_user_id (user_id)`

## 3) pay 模块（`pa_`）

### `pa_platform`（来源：`qd_payment_platform`）
用途：充值/提现通道配置（币种、限额、展示开关、汇率、第三方配置等）。

关键字段（节选）：
- 通道：`id`, `name`, `show_name`, `currency_type`, `currency_code`, `unit`, `coin`
- 限额：`min_recharge_money`, `min_withdraw_money`, `max_withdraw_money`, `fast_amount`
- 开关：`recharge_is_show`, `withdraw_is_show`, `status`, `is_first_charge`
- 配置：`config`（text/json）, `instruction`, `exchange_rate`, `exchange_update_time`, `remark`
- 时间：`create_time`, `update_time`, `delete_time`

### `pa_recharge_order`（来源：`qd_recharge`）
用途：充值订单、凭证、第三方订单号、回调结果、归集信息等。

关键字段（节选）：
- 订单：`id`, `order_id`, `user_id`, `is_xuni`, `type`, `status`, `create_time`, `update_time`, `pay_time`, `success_time`
- 金额：`money`, `original_money`, `exchange_rate`, `coin`, `coin_money`
- 通道与凭证：`pay_id`, `trade_no`, `hash`, `txid`, `img`, `extra`, `result`, `ip`, `admin_id`
- 归集：`collection_status`, `collection_money`, `batch_no`
- 活动：`activity_id`, `recharge_bonus_id`
- 来源：`from`（1-前端 2-后台）

索引（建议至少保留）：
- `idx_status_type (status, type)`
- `idx_order_id (order_id)`

## 4) wallet 模块（`me_user_water`）

### `me_user_water`（来源：`qd_money_log`）
用途：用户账变流水（多钱包 type + 明细 detail_type）。

关键字段（节选）：
- 主体：`id`, `user_id`, `admin_id`, `from_user_id`, `order_id`, `is_xuni`
- 账变：`change`（1+ 2-）, `money`, `user_money`（操作前）, `last_money`（操作后）
- 分类：`type`（钱包类型）, `detail_type`（明细类型）, `title`, `remarks`
- 时间：`create_time`, `status`

索引：参考库索引较多，迁移第一阶段建议至少保留：
- `idx_user_id_type_create_time (user_id, type, create_time)`
- `idx_user_detail_type (user_id, detail_type)`
- `idx_type_create_time (type, create_time)`

## 5) sys 模块（`sy_`）

### `sy_admin_logs`（来源：`qd_operation_log`）
用途：后台操作日志（参考库 `qd_operation_log` 结构较简单，现项目使用 `sy_admin_logs`）。

字段：`admin_id`, `username`, `action`, `content`, `user_id`, `ip`, `create_time`, `update_time`

> 现项目使用 `AdminLogModel`（`sy_admin_logs`）与 `/admin/logs` 接口，无需另建 `sys_operation_log`。

