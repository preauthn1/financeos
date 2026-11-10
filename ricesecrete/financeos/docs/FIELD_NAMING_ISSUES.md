# 表字段命名不规范清单

> **2026-07-09 已批量重命名**：见 `config/field_renames.php` + `scripts/apply_field_renames.php`  
> 未改动：`me_user.leader1~7`、`registered_at`（需单独迁移窗口）

> 自动生成：`php scripts/audit_field_naming.php`  
> 规范参考：[MODEL_SCHEMA_STANDARD.md](./MODEL_SCHEMA_STANDARD.md)

## 概览

| 指标 | 数量 |
|------|------|
| 扫描模型 | 71 |
| 有问题表 | 42 |
| 问题项 | 122 |
| 规范表（无问题） | 29 |

## 优先级建议

| 优先级 | 范围 | 说明 |
|--------|------|------|
| **P0 高** | `pa_recharge_order`、`pa_withdraw_order`、`me_user` | 订单号/外键/金额/时间混用，影响全栈理解 |
| **P1 中** | `ad_*`、`iv_*`、`act_*`、`toc_*` 共 26 张 | 遗留 `*_time` + unix `created_at`，与规范 datetime 不一致 |
| **P2 低** | 日志表、`img`/`pic`、布尔前缀 | 语义可接受，新表再统一即可 |

### P0 重点表（建议优先治理）

| 表 | 主要问题 |
|----|----------|
| `pa_recharge_order` | `order_id` 实为字符串订单号、`from` 歧义、`pay_id`/`hash`/`txid`、`pay_time` 类型混用、`*_money` |
| `pa_withdraw_order` | 同上 + `withdraw_money`/`convert_money` |
| `me_user` | `leader1~7` 扁平上级、`registered_at` 与 `created_at` 重复、`transfer_enabled` 无 `is_` 前缀 |

### 已符合规范（29 张，节选）

`me_user_water`、`me_user_message`、`me_user_card`、`me_user_balance`、`sy_admins`、`sy_roles`、`co_articles`、`co_notices` 等 — 字段命名与 [MODEL_SCHEMA_STANDARD.md](./MODEL_SCHEMA_STANDARD.md) 一致。

## 按问题类型

### 时间后缀 *_time（31）

| 表 | 字段 | 说明 | 建议 |
|----|------|------|------|
| `act_activity` | `end_time` | 建议改为 *_at（unix）或 Laravel timestamps | end_at |
| `act_activity` | `expiration_time` | 建议改为 *_at（unix）或 Laravel timestamps | expiration_at |
| `act_activity` | `start_time` | 建议改为 *_at（unix）或 Laravel timestamps | start_at |
| `act_activity_record` | `return_time` | 建议改为 *_at（unix）或 Laravel timestamps | return_at |
| `act_pdd_turntable` | `end_time` | 建议改为 *_at（unix）或 Laravel timestamps | end_at |
| `act_pdd_turntable` | `start_time` | 建议改为 *_at（unix）或 Laravel timestamps | start_at |
| `ad_config` | `level_time` | 建议改为 *_at（unix）或 Laravel timestamps | level_at |
| `ad_config` | `password_expire_time` | 建议改为 *_at（unix）或 Laravel timestamps | password_expire_at |
| `ad_product_order` | `buy_time` | 建议改为 *_at（unix）或 Laravel timestamps | buy_at |
| `ad_product_order` | `end_time` | 建议改为 *_at（unix）或 Laravel timestamps | end_at |
| `ad_product_order` | `match_time` | 建议改为 *_at（unix）或 Laravel timestamps | match_at |
| `ad_product_order` | `settlement_time` | 建议改为 *_at（unix）或 Laravel timestamps | settlement_at |
| `ad_product_order` | `start_time` | 建议改为 *_at（unix）或 Laravel timestamps | start_at |
| `iv_product` | `end_time` | 建议改为 *_at（unix）或 Laravel timestamps | end_at |
| `iv_product` | `rebate_time` | 建议改为 *_at（unix）或 Laravel timestamps | rebate_at |
| `iv_product` | `start_time` | 建议改为 *_at（unix）或 Laravel timestamps | start_at |
| `iv_product_order` | `end_time` | 建议改为 *_at（unix）或 Laravel timestamps | end_at |
| `iv_product_order` | `settlement_time` | 建议改为 *_at（unix）或 Laravel timestamps | settlement_at |
| `iv_product_order` | `start_time` | 建议改为 *_at（unix）或 Laravel timestamps | start_at |
| `me_finance_plan` | `end_time` | 建议改为 *_at（unix）或 Laravel timestamps | end_at |
| `me_finance_plan` | `start_time` | 建议改为 *_at（unix）或 Laravel timestamps | start_at |
| `me_level_team` | `overdue_time` | 建议改为 *_at（unix）或 Laravel timestamps | overdue_at |
| `me_level_team_invest_time` | `end_time` | 建议改为 *_at（unix）或 Laravel timestamps | end_at |
| `me_level_team_invest_time` | `start_time` | 建议改为 *_at（unix）或 Laravel timestamps | start_at |
| `me_team_active_reward_v2` | `receive_time` | 建议改为 *_at（unix）或 Laravel timestamps | receive_at |
| `pa_platform` | `exchange_update_time` | 建议改为 *_at（unix）或 Laravel timestamps | exchange_update_at |
| `pa_recharge_order` | `pay_time` | 建议改为 *_at（unix）或 Laravel timestamps | pay_at |
| `pa_recharge_order` | `success_time` | 建议改为 *_at（unix）或 Laravel timestamps | success_at |
| `pa_withdraw_order` | `check_time` | 建议改为 *_at（unix）或 Laravel timestamps | check_at |
| `pa_withdraw_order` | `pay_time` | 建议改为 *_at（unix）或 Laravel timestamps | pay_at |
| `toc_operation_center` | `auth_time` | 建议改为 *_at（unix）或 Laravel timestamps | auth_at |

### 时间类型（26）

| 表 | 字段 | 说明 | 建议 |
|----|------|------|------|
| `act_activity` | `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |
| `act_activity_record` | `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |
| `act_pdd_turntable` | `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |
| `act_pdd_turntable_count` | `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |
| `act_pdd_turntable_record` | `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |
| `act_turntable` | `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |
| `act_turntable_count` | `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |
| `ad_advertis_num` | `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |
| `ad_advertis_space` | `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |
| `ad_config` | `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |
| `ad_plan` | `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |
| `ad_plan_user` | `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |
| `ad_product_order` | `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |
| `co_video` | `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |
| `iv_product` | `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |
| `iv_product_order` | `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |
| `me_finance_plan` | `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |
| `me_finance_plan_task` | `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |
| `me_level_team` | `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |
| `me_level_team_invest_time` | `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |
| `me_promotion_reward_day` | `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |
| `me_task_summary_v2` | `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |
| `me_team_active_reward` | `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |
| `me_team_active_reward_v2` | `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |
| `toc_operation_center` | `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |
| `toc_operation_center_issue` | `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |

### 金额命名（20）

| 表 | 字段 | 说明 | 建议 |
|----|------|------|------|
| `act_activity` | `user_money` | 流水用 amount，订单用 *_money，体系不统一 | user_amount |
| `act_turntable_log` | `money` | 建议 amount 或具业务前缀如 recharge_amount | amount |
| `ad_advertis_space` | `cpm_cost_money` | 流水用 amount，订单用 *_money，体系不统一 | cpm_cost_amount |
| `ad_advertis_space` | `turnover_money` | 流水用 amount，订单用 *_money，体系不统一 | turnover_amount |
| `ad_config` | `invite_num_money` | 流水用 amount，订单用 *_money，体系不统一 | invite_num_amount |
| `ad_config` | `withdraw_trx_min_money` | 流水用 amount，订单用 *_money，体系不统一 | withdraw_trx_min_amount |
| `ad_config` | `withdraw_usdt_min_money` | 流水用 amount，订单用 *_money，体系不统一 | withdraw_usdt_min_amount |
| `ad_product_order` | `money` | 建议 amount 或具业务前缀如 recharge_amount | amount |
| `ad_product_order` | `overdue_money` | 流水用 amount，订单用 *_money，体系不统一 | overdue_amount |
| `ad_product_order` | `profit_money` | 流水用 amount，订单用 *_money，体系不统一 | profit_amount |
| `iv_product` | `give_money` | 流水用 amount，订单用 *_money，体系不统一 | give_amount |
| `iv_product_order` | `profit_money` | 流水用 amount，订单用 *_money，体系不统一 | profit_amount |
| `pa_platform` | `max_withdraw_money` | 流水用 amount，订单用 *_money，体系不统一 | max_withdraw_amount |
| `pa_platform` | `min_recharge_money` | 流水用 amount，订单用 *_money，体系不统一 | min_recharge_amount |
| `pa_platform` | `min_withdraw_money` | 流水用 amount，订单用 *_money，体系不统一 | min_withdraw_amount |
| `pa_recharge_order` | `coin_money` | 流水用 amount，订单用 *_money，体系不统一 | coin_amount |
| `pa_recharge_order` | `original_money` | 流水用 amount，订单用 *_money，体系不统一 | original_amount |
| `pa_withdraw_order` | `convert_money` | 流水用 amount，订单用 *_money，体系不统一 | convert_amount |
| `pa_withdraw_order` | `original_money` | 流水用 amount，订单用 *_money，体系不统一 | original_amount |
| `pa_withdraw_order` | `withdraw_money` | 流水用 amount，订单用 *_money，体系不统一 | withdraw_amount |

### 时间字段不成对（6）

| 表 | 字段 | 说明 | 建议 |
|----|------|------|------|
| `act_turntable_log` | `updated_at` | 仅有 created_at 或 updated_at | 日志表可仅 created_at |
| `me_user_log` | `updated_at` | 仅有 created_at 或 updated_at | 日志表可仅 created_at |
| `me_user_login` | `updated_at` | 仅有 created_at 或 updated_at | 日志表可仅 created_at |
| `me_user_virtual_log` | `updated_at` | 仅有 created_at 或 updated_at | 日志表可仅 created_at |
| `me_user_water` | `updated_at` | 仅有 created_at 或 updated_at | 日志表可仅 created_at |
| `sy_admin_logs` | `updated_at` | 仅有 created_at 或 updated_at | 日志表可仅 created_at |

### 资源字段（7）

| 表 | 字段 | 说明 | 建议 |
|----|------|------|------|
| `act_turntable_prize` | `img` | 图片/凭证建议 image_url 或 voucher_url | img_url |
| `ad_config` | `img` | 图片/凭证建议 image_url 或 voucher_url | img_url |
| `co_banners` | `pic` | 图片/凭证建议 image_url 或 voucher_url | pic_url |
| `co_customer_services` | `image` | 图片/凭证建议 image_url 或 voucher_url | image_url |
| `co_video` | `pic` | 图片/凭证建议 image_url 或 voucher_url | pic_url |
| `pa_platform` | `image` | 图片/凭证建议 image_url 或 voucher_url | image_url |
| `pa_recharge_order` | `img` | 图片/凭证建议 image_url 或 voucher_url | img_url |

### 冗余字段（1）

| 表 | 字段 | 说明 | 建议 |
|----|------|------|------|
| `ad_product_order` | `finance_product_name` | 产品名应通过 finance_product_id 关联查询 | 移除冗余列 |

### 上级字段扁平化（7）

| 表 | 字段 | 说明 | 建议 |
|----|------|------|------|
| `me_user` | `leader1` | leader1~7 扩展性差，新表建议 relation 表或 parent_user_id | user_relation 表 |
| `me_user` | `leader2` | leader1~7 扩展性差，新表建议 relation 表或 parent_user_id | user_relation 表 |
| `me_user` | `leader3` | leader1~7 扩展性差，新表建议 relation 表或 parent_user_id | user_relation 表 |
| `me_user` | `leader4` | leader1~7 扩展性差，新表建议 relation 表或 parent_user_id | user_relation 表 |
| `me_user` | `leader5` | leader1~7 扩展性差，新表建议 relation 表或 parent_user_id | user_relation 表 |
| `me_user` | `leader6` | leader1~7 扩展性差，新表建议 relation 表或 parent_user_id | user_relation 表 |
| `me_user` | `leader7` | leader1~7 扩展性差，新表建议 relation 表或 parent_user_id | user_relation 表 |

### 外键类型（7）

| 表 | 字段 | 说明 | 建议 |
|----|------|------|------|
| `me_user` | `leader1` | user_id 关联建议 unsignedBigInteger 与主表一致 | unsignedBigInteger |
| `me_user` | `leader2` | user_id 关联建议 unsignedBigInteger 与主表一致 | unsignedBigInteger |
| `me_user` | `leader3` | user_id 关联建议 unsignedBigInteger 与主表一致 | unsignedBigInteger |
| `me_user` | `leader4` | user_id 关联建议 unsignedBigInteger 与主表一致 | unsignedBigInteger |
| `me_user` | `leader5` | user_id 关联建议 unsignedBigInteger 与主表一致 | unsignedBigInteger |
| `me_user` | `leader6` | user_id 关联建议 unsignedBigInteger 与主表一致 | unsignedBigInteger |
| `me_user` | `leader7` | user_id 关联建议 unsignedBigInteger 与主表一致 | unsignedBigInteger |

### 布尔前缀（2）

| 表 | 字段 | 说明 | 建议 |
|----|------|------|------|
| `me_user` | `invite_code_status` | 开关类建议 is_* 前缀 | is_invite_code_status |
| `me_user` | `transfer_enabled` | 开关类建议 is_* 前缀 | is_transfer_enabled |

### 时间语义重复（1）

| 表 | 字段 | 说明 | 建议 |
|----|------|------|------|
| `me_user` | `registered_at` | 与 created_at 重叠 | 保留 created_at 或明确业务差异 |

### 布尔命名（2）

| 表 | 字段 | 说明 | 建议 |
|----|------|------|------|
| `me_user_ad_countdown_config` | `no_can_click` | 否定+动作混合，建议 can_click_when_no / can_click_when_yes | can_click_on_no / can_click_on_yes |
| `me_user_ad_countdown_config` | `yes_can_click` | 否定+动作混合，建议 can_click_when_no / can_click_when_yes | can_click_on_no / can_click_on_yes |

### 布尔语义（1）

| 表 | 字段 | 说明 | 建议 |
|----|------|------|------|
| `me_user_message` | `is_pop` | is_pop 缩写不清 | is_popup |

### 管理员外键（1）

| 表 | 字段 | 说明 | 建议 |
|----|------|------|------|
| `me_user_real_name_auth` | `auditor_id` | 后台操作人建议统一 admin_id | admin_id |

### 外键命名（1）

| 表 | 字段 | 说明 | 建议 |
|----|------|------|------|
| `pa_recharge_order` | `pay_id` | 支付通道 ID 语义不清 | pay_platform_id |

### 字段缩写（2）

| 表 | 字段 | 说明 | 建议 |
|----|------|------|------|
| `pa_recharge_order` | `hash` | 链上字段建议 transaction_hash | transaction_hash |
| `pa_recharge_order` | `txid` | 链上字段建议 transaction_hash | transaction_hash |

### 时间类型混用（2）

| 表 | 字段 | 说明 | 建议 |
|----|------|------|------|
| `pa_recharge_order` | `pay_time` | 同表其他时间为 unix bigint，pay_time 为 timestamp | paid_at (unix) |
| `pa_withdraw_order` | `pay_time` | 同表其他时间为 unix bigint，pay_time 为 timestamp | paid_at (unix) |

### 歧义字段名（1）

| 表 | 字段 | 说明 | 建议 |
|----|------|------|------|
| `pa_recharge_order` | `from` | from 易混淆，语义为订单来源 | source 或 order_source |

### 订单号命名（2）

| 表 | 字段 | 说明 | 建议 |
|----|------|------|------|
| `pa_recharge_order` | `order_id` | 字符串订单号应为 order_no，order_id 保留数值 FK | order_no |
| `pa_withdraw_order` | `order_id` | 字符串订单号应为 order_no，order_id 保留数值 FK | order_no |

### 用户外键（2）

| 表 | 字段 | 说明 | 建议 |
|----|------|------|------|
| `toc_operation_center` | `submit_user_id` | 与 admin_id 混用场景，建议区分 submit_admin_id / submit_user_id 文档化 | 明确操作端 |
| `toc_operation_center_issue` | `submit_user_id` | 与 admin_id 混用场景，建议区分 submit_admin_id / submit_user_id 文档化 | 明确操作端 |

## 按表汇总

### `act_activity`（5 项）

问题类型：时间后缀 *_time、时间类型、金额命名

| 字段 | 问题 | 建议 |
|------|------|------|
| `end_time` | 建议改为 *_at（unix）或 Laravel timestamps | end_at |
| `expiration_time` | 建议改为 *_at（unix）或 Laravel timestamps | expiration_at |
| `start_time` | 建议改为 *_at（unix）或 Laravel timestamps | start_at |
| `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |
| `user_money` | 流水用 amount，订单用 *_money，体系不统一 | user_amount |

### `act_activity_record`（2 项）

问题类型：时间后缀 *_time、时间类型

| 字段 | 问题 | 建议 |
|------|------|------|
| `return_time` | 建议改为 *_at（unix）或 Laravel timestamps | return_at |
| `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |

### `act_pdd_turntable`（3 项）

问题类型：时间后缀 *_time、时间类型

| 字段 | 问题 | 建议 |
|------|------|------|
| `end_time` | 建议改为 *_at（unix）或 Laravel timestamps | end_at |
| `start_time` | 建议改为 *_at（unix）或 Laravel timestamps | start_at |
| `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |

### `act_pdd_turntable_count`（1 项）

问题类型：时间类型

| 字段 | 问题 | 建议 |
|------|------|------|
| `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |

### `act_pdd_turntable_record`（1 项）

问题类型：时间类型

| 字段 | 问题 | 建议 |
|------|------|------|
| `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |

### `act_turntable`（1 项）

问题类型：时间类型

| 字段 | 问题 | 建议 |
|------|------|------|
| `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |

### `act_turntable_count`（1 项）

问题类型：时间类型

| 字段 | 问题 | 建议 |
|------|------|------|
| `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |

### `act_turntable_log`（2 项）

问题类型：时间字段不成对、金额命名

| 字段 | 问题 | 建议 |
|------|------|------|
| `updated_at` | 仅有 created_at 或 updated_at | 日志表可仅 created_at |
| `money` | 建议 amount 或具业务前缀如 recharge_amount | amount |

### `act_turntable_prize`（1 项）

问题类型：资源字段

| 字段 | 问题 | 建议 |
|------|------|------|
| `img` | 图片/凭证建议 image_url 或 voucher_url | img_url |

### `ad_advertis_num`（1 项）

问题类型：时间类型

| 字段 | 问题 | 建议 |
|------|------|------|
| `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |

### `ad_advertis_space`（3 项）

问题类型：时间类型、金额命名

| 字段 | 问题 | 建议 |
|------|------|------|
| `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |
| `cpm_cost_money` | 流水用 amount，订单用 *_money，体系不统一 | cpm_cost_amount |
| `turnover_money` | 流水用 amount，订单用 *_money，体系不统一 | turnover_amount |

### `ad_config`（7 项）

问题类型：时间后缀 *_time、时间类型、资源字段、金额命名

| 字段 | 问题 | 建议 |
|------|------|------|
| `level_time` | 建议改为 *_at（unix）或 Laravel timestamps | level_at |
| `password_expire_time` | 建议改为 *_at（unix）或 Laravel timestamps | password_expire_at |
| `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |
| `img` | 图片/凭证建议 image_url 或 voucher_url | img_url |
| `invite_num_money` | 流水用 amount，订单用 *_money，体系不统一 | invite_num_amount |
| `withdraw_trx_min_money` | 流水用 amount，订单用 *_money，体系不统一 | withdraw_trx_min_amount |
| `withdraw_usdt_min_money` | 流水用 amount，订单用 *_money，体系不统一 | withdraw_usdt_min_amount |

### `ad_plan`（1 项）

问题类型：时间类型

| 字段 | 问题 | 建议 |
|------|------|------|
| `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |

### `ad_plan_user`（1 项）

问题类型：时间类型

| 字段 | 问题 | 建议 |
|------|------|------|
| `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |

### `ad_product_order`（10 项）

问题类型：冗余字段、时间后缀 *_time、时间类型、金额命名

| 字段 | 问题 | 建议 |
|------|------|------|
| `finance_product_name` | 产品名应通过 finance_product_id 关联查询 | 移除冗余列 |
| `buy_time` | 建议改为 *_at（unix）或 Laravel timestamps | buy_at |
| `end_time` | 建议改为 *_at（unix）或 Laravel timestamps | end_at |
| `match_time` | 建议改为 *_at（unix）或 Laravel timestamps | match_at |
| `settlement_time` | 建议改为 *_at（unix）或 Laravel timestamps | settlement_at |
| `start_time` | 建议改为 *_at（unix）或 Laravel timestamps | start_at |
| `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |
| `money` | 建议 amount 或具业务前缀如 recharge_amount | amount |
| `overdue_money` | 流水用 amount，订单用 *_money，体系不统一 | overdue_amount |
| `profit_money` | 流水用 amount，订单用 *_money，体系不统一 | profit_amount |

### `co_banners`（1 项）

问题类型：资源字段

| 字段 | 问题 | 建议 |
|------|------|------|
| `pic` | 图片/凭证建议 image_url 或 voucher_url | pic_url |

### `co_customer_services`（1 项）

问题类型：资源字段

| 字段 | 问题 | 建议 |
|------|------|------|
| `image` | 图片/凭证建议 image_url 或 voucher_url | image_url |

### `co_video`（2 项）

问题类型：时间类型、资源字段

| 字段 | 问题 | 建议 |
|------|------|------|
| `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |
| `pic` | 图片/凭证建议 image_url 或 voucher_url | pic_url |

### `iv_product`（5 项）

问题类型：时间后缀 *_time、时间类型、金额命名

| 字段 | 问题 | 建议 |
|------|------|------|
| `end_time` | 建议改为 *_at（unix）或 Laravel timestamps | end_at |
| `rebate_time` | 建议改为 *_at（unix）或 Laravel timestamps | rebate_at |
| `start_time` | 建议改为 *_at（unix）或 Laravel timestamps | start_at |
| `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |
| `give_money` | 流水用 amount，订单用 *_money，体系不统一 | give_amount |

### `iv_product_order`（5 项）

问题类型：时间后缀 *_time、时间类型、金额命名

| 字段 | 问题 | 建议 |
|------|------|------|
| `end_time` | 建议改为 *_at（unix）或 Laravel timestamps | end_at |
| `settlement_time` | 建议改为 *_at（unix）或 Laravel timestamps | settlement_at |
| `start_time` | 建议改为 *_at（unix）或 Laravel timestamps | start_at |
| `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |
| `profit_money` | 流水用 amount，订单用 *_money，体系不统一 | profit_amount |

### `me_finance_plan`（3 项）

问题类型：时间后缀 *_time、时间类型

| 字段 | 问题 | 建议 |
|------|------|------|
| `end_time` | 建议改为 *_at（unix）或 Laravel timestamps | end_at |
| `start_time` | 建议改为 *_at（unix）或 Laravel timestamps | start_at |
| `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |

### `me_finance_plan_task`（1 项）

问题类型：时间类型

| 字段 | 问题 | 建议 |
|------|------|------|
| `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |

### `me_level_team`（2 项）

问题类型：时间后缀 *_time、时间类型

| 字段 | 问题 | 建议 |
|------|------|------|
| `overdue_time` | 建议改为 *_at（unix）或 Laravel timestamps | overdue_at |
| `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |

### `me_level_team_invest_time`（3 项）

问题类型：时间后缀 *_time、时间类型

| 字段 | 问题 | 建议 |
|------|------|------|
| `end_time` | 建议改为 *_at（unix）或 Laravel timestamps | end_at |
| `start_time` | 建议改为 *_at（unix）或 Laravel timestamps | start_at |
| `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |

### `me_promotion_reward_day`（1 项）

问题类型：时间类型

| 字段 | 问题 | 建议 |
|------|------|------|
| `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |

### `me_task_summary_v2`（1 项）

问题类型：时间类型

| 字段 | 问题 | 建议 |
|------|------|------|
| `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |

### `me_team_active_reward`（1 项）

问题类型：时间类型

| 字段 | 问题 | 建议 |
|------|------|------|
| `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |

### `me_team_active_reward_v2`（2 项）

问题类型：时间后缀 *_time、时间类型

| 字段 | 问题 | 建议 |
|------|------|------|
| `receive_time` | 建议改为 *_at（unix）或 Laravel timestamps | receive_at |
| `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |

### `me_user`（17 项）

问题类型：上级字段扁平化、外键类型、布尔前缀、时间语义重复

| 字段 | 问题 | 建议 |
|------|------|------|
| `leader1` | leader1~7 扩展性差，新表建议 relation 表或 parent_user_id | user_relation 表 |
| `leader2` | leader1~7 扩展性差，新表建议 relation 表或 parent_user_id | user_relation 表 |
| `leader3` | leader1~7 扩展性差，新表建议 relation 表或 parent_user_id | user_relation 表 |
| `leader4` | leader1~7 扩展性差，新表建议 relation 表或 parent_user_id | user_relation 表 |
| `leader5` | leader1~7 扩展性差，新表建议 relation 表或 parent_user_id | user_relation 表 |
| `leader6` | leader1~7 扩展性差，新表建议 relation 表或 parent_user_id | user_relation 表 |
| `leader7` | leader1~7 扩展性差，新表建议 relation 表或 parent_user_id | user_relation 表 |
| `leader1` | user_id 关联建议 unsignedBigInteger 与主表一致 | unsignedBigInteger |
| `leader2` | user_id 关联建议 unsignedBigInteger 与主表一致 | unsignedBigInteger |
| `leader3` | user_id 关联建议 unsignedBigInteger 与主表一致 | unsignedBigInteger |
| `leader4` | user_id 关联建议 unsignedBigInteger 与主表一致 | unsignedBigInteger |
| `leader5` | user_id 关联建议 unsignedBigInteger 与主表一致 | unsignedBigInteger |
| `leader6` | user_id 关联建议 unsignedBigInteger 与主表一致 | unsignedBigInteger |
| `leader7` | user_id 关联建议 unsignedBigInteger 与主表一致 | unsignedBigInteger |
| `invite_code_status` | 开关类建议 is_* 前缀 | is_invite_code_status |
| `transfer_enabled` | 开关类建议 is_* 前缀 | is_transfer_enabled |
| `registered_at` | 与 created_at 重叠 | 保留 created_at 或明确业务差异 |

### `me_user_ad_countdown_config`（2 项）

问题类型：布尔命名

| 字段 | 问题 | 建议 |
|------|------|------|
| `no_can_click` | 否定+动作混合，建议 can_click_when_no / can_click_when_yes | can_click_on_no / can_click_on_yes |
| `yes_can_click` | 否定+动作混合，建议 can_click_when_no / can_click_when_yes | can_click_on_no / can_click_on_yes |

### `me_user_log`（1 项）

问题类型：时间字段不成对

| 字段 | 问题 | 建议 |
|------|------|------|
| `updated_at` | 仅有 created_at 或 updated_at | 日志表可仅 created_at |

### `me_user_login`（1 项）

问题类型：时间字段不成对

| 字段 | 问题 | 建议 |
|------|------|------|
| `updated_at` | 仅有 created_at 或 updated_at | 日志表可仅 created_at |

### `me_user_message`（1 项）

问题类型：布尔语义

| 字段 | 问题 | 建议 |
|------|------|------|
| `is_pop` | is_pop 缩写不清 | is_popup |

### `me_user_real_name_auth`（1 项）

问题类型：管理员外键

| 字段 | 问题 | 建议 |
|------|------|------|
| `auditor_id` | 后台操作人建议统一 admin_id | admin_id |

### `me_user_virtual_log`（1 项）

问题类型：时间字段不成对

| 字段 | 问题 | 建议 |
|------|------|------|
| `updated_at` | 仅有 created_at 或 updated_at | 日志表可仅 created_at |

### `me_user_water`（1 项）

问题类型：时间字段不成对

| 字段 | 问题 | 建议 |
|------|------|------|
| `updated_at` | 仅有 created_at 或 updated_at | 日志表可仅 created_at |

### `pa_platform`（5 项）

问题类型：时间后缀 *_time、资源字段、金额命名

| 字段 | 问题 | 建议 |
|------|------|------|
| `exchange_update_time` | 建议改为 *_at（unix）或 Laravel timestamps | exchange_update_at |
| `image` | 图片/凭证建议 image_url 或 voucher_url | image_url |
| `max_withdraw_money` | 流水用 amount，订单用 *_money，体系不统一 | max_withdraw_amount |
| `min_recharge_money` | 流水用 amount，订单用 *_money，体系不统一 | min_recharge_amount |
| `min_withdraw_money` | 流水用 amount，订单用 *_money，体系不统一 | min_withdraw_amount |

### `pa_recharge_order`（11 项）

问题类型：外键命名、字段缩写、时间后缀 *_time、时间类型混用、歧义字段名、订单号命名、资源字段、金额命名

| 字段 | 问题 | 建议 |
|------|------|------|
| `pay_id` | 支付通道 ID 语义不清 | pay_platform_id |
| `hash` | 链上字段建议 transaction_hash | transaction_hash |
| `txid` | 链上字段建议 transaction_hash | transaction_hash |
| `pay_time` | 建议改为 *_at（unix）或 Laravel timestamps | pay_at |
| `success_time` | 建议改为 *_at（unix）或 Laravel timestamps | success_at |
| `pay_time` | 同表其他时间为 unix bigint，pay_time 为 timestamp | paid_at (unix) |
| `from` | from 易混淆，语义为订单来源 | source 或 order_source |
| `order_id` | 字符串订单号应为 order_no，order_id 保留数值 FK | order_no |
| `img` | 图片/凭证建议 image_url 或 voucher_url | img_url |
| `coin_money` | 流水用 amount，订单用 *_money，体系不统一 | coin_amount |
| `original_money` | 流水用 amount，订单用 *_money，体系不统一 | original_amount |

### `pa_withdraw_order`（7 项）

问题类型：时间后缀 *_time、时间类型混用、订单号命名、金额命名

| 字段 | 问题 | 建议 |
|------|------|------|
| `check_time` | 建议改为 *_at（unix）或 Laravel timestamps | check_at |
| `pay_time` | 建议改为 *_at（unix）或 Laravel timestamps | pay_at |
| `pay_time` | 同表其他时间为 unix bigint，pay_time 为 timestamp | paid_at (unix) |
| `order_id` | 字符串订单号应为 order_no，order_id 保留数值 FK | order_no |
| `convert_money` | 流水用 amount，订单用 *_money，体系不统一 | convert_amount |
| `original_money` | 流水用 amount，订单用 *_money，体系不统一 | original_amount |
| `withdraw_money` | 流水用 amount，订单用 *_money，体系不统一 | withdraw_amount |

### `sy_admin_logs`（1 项）

问题类型：时间字段不成对

| 字段 | 问题 | 建议 |
|------|------|------|
| `updated_at` | 仅有 created_at 或 updated_at | 日志表可仅 created_at |

### `toc_operation_center`（3 项）

问题类型：时间后缀 *_time、时间类型、用户外键

| 字段 | 问题 | 建议 |
|------|------|------|
| `auth_time` | 建议改为 *_at（unix）或 Laravel timestamps | auth_at |
| `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |
| `submit_user_id` | 与 admin_id 混用场景，建议区分 submit_admin_id / submit_user_id 文档化 | 明确操作端 |

### `toc_operation_center_issue`（2 项）

问题类型：时间类型、用户外键

| 字段 | 问题 | 建议 |
|------|------|------|
| `created_at/updated_at` | 无 timestamps 但用 unix，与实体表 datetime 规范不一致 | 实体表用 timestamps；日志表仅 created_at unix |
| `submit_user_id` | 与 admin_id 混用场景，建议区分 submit_admin_id / submit_user_id 文档化 | 明确操作端 |
