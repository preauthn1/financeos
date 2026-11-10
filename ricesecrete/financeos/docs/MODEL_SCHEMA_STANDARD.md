# 模型表结构规范与分区方案

本文档基于 `app/model` 下 71 个 `DefinesTableSchema` 模型整理，定义命名规范、字段约定，以及分区策略。

## 一、表名前缀规范（已落地，暂不批量改名）

| 前缀 | 模块 | 示例 | 说明 |
|------|------|------|------|
| `me_` | 会员 member | `me_user`, `me_user_water` | 用户主数据 + 子表 |
| `sy_` | 系统 sys | `sy_admins`, `sy_roles` | 后台 RBAC、配置 |
| `co_` | 内容 content | `co_articles`, `co_video` | CMS、运营内容 |
| `pa_` | 支付 pay | `pa_recharge_order`, `pa_platform` | 充值/提现/通道 |
| `ad_` | 广告 ad | `ad_config`, `ad_product_order` | 广告位、库存订单 |
| `iv_` | 投资 invest | `iv_product`, `iv_product_order` | AI 托管 |
| `act_` | 活动 activity | `act_activity`, `act_turntable_log` | 抽奖、活动 |
| `toc_` | 运营 ops | `toc_operation_center` | 运营中心 |

### 表名单复数约定（新表遵循）

- **配置/实体主表**：单数，如 `me_user`, `ad_config`, `iv_product`
- **多对多/关联表**：复数，如 `sy_admin_roles`, `sy_role_permissions`
- **内容运营表**：复数，如 `co_articles`, `co_banners`
- **订单/流水**：单数 `_order` / `_log`，如 `pa_recharge_order`, `me_user_water`

> 历史表如 `co_video`（单数）、`sy_admins`（复数）保持不动，避免迁移成本。

---

## 二、字段命名规范

### 通用字段

| 字段 | 类型 | 适用 | 说明 |
|------|------|------|------|
| `id` | bigint/increments | 全部 | 主键 |
| `user_id` | unsignedBigInteger | 会员子表 | 关联 `me_user.id`，**逻辑关联，无 DB 外键** |
| `admin_id` | unsignedBigInteger | 后台操作 | 关联 `sy_admins.id` |
| `status` | tinyint | 状态机 | 0/1 或枚举，须在 comment 写明含义 |
| `remark` | string(255) | 备注 | 统一用 `remark`，API 层 `remarks` 映射到此字段 |
| `created_at` | 见下 | 时间 | 见「时间字段规范」 |
| `updated_at` | 见下 | 时间 | 可更新业务表必须有 |
| `deleted_at` | timestamp | 软删除 | 仅主实体表 |

### 资金/流水字段（`me_user_water` 等）

| 字段 | 说明 |
|------|------|
| `type` | 钱包类型，见 `UserWaterType` |
| `detail_type` | 明细类型，见 `UserWaterDetailType` |
| `direction` | 1=收入 2=支出 |
| `amount` | 变动金额（正数） |
| `before_balance` / `after_balance` | 变动前后余额 |
| `order_id` | 关联业务订单 ID |

### 钱包字段（`me_user`）

| 字段 | 流水 type | 说明 |
|------|-----------|------|
| `balance` | 1 基础 | 可提现主钱包 |
| `recharge_balance` | 2 充值 | 充值记录钱包 |
| `trial_balance` | 3 彩金 | 体验/彩金 |
| `frozen_balance` | 4 冻结 | 提现冻结 |
| `brokerage_balance` | 8 佣金池 | 佣金池余额 |

### 时间字段规范

| 表类型 | `created_at` / `updated_at` | 示例 |
|--------|----------------------------|------|
| 后台实体表 | Laravel `timestamps => true`（datetime） | `me_user`, `sy_admins`, `co_articles` |
| 追加型日志表 | `unsignedBigInteger` unix，仅 `created_at` | `me_user_water`, `me_user_log` |
| 遗留业务表 | 手动 unix 字段 `start_time`/`end_time` | `ad_*`, `act_*`, `iv_*` |
| 后台操作日志 | `timestamp` 单列 | `sy_admin_logs` |

**新日志表统一**：`created_at` = `unsignedBigInteger` unix，无 `updated_at`。

---

## 三、全表分类（71 张）

### 3.1 不适合分区（主数据 / 配置 / 关联）

`me_user`, `me_user_card`, `me_user_virtual`, `me_user_message`, `me_user_ip`, `me_user_level`, `me_user_balance`, `me_user_brokerage_pool`, `me_user_address`, `me_user_credit_score`, `me_user_ad_*_config`, `me_finance_plan*`, `me_level_team*`, `me_team_active_reward*`, `me_promotion_reward_day`, `me_task_summary_v2`, 全部 `sy_*`, 全部 `co_*`（除日志）, `ad_config`, `ad_plan*`, `iv_product`, `act_activity`, `act_turntable`, `act_turntable_prize`, `act_pdd_turntable`, `pa_platform`, `toc_operation_center*`

### 3.2 可选分区（订单类，数据量大后再做）

`pa_recharge_order`, `pa_withdraw_order`, `ad_product_order`, `iv_product_order`, `act_activity_record`, `act_pdd_turntable_record`

分区键建议：Laravel `created_at`（datetime）按月 `RANGE COLUMNS`。

### 3.3 已配置分区（追加型日志）

| 表 | 模型 | 分区键 | 主键 |
|----|------|--------|------|
| `me_user_water` | UserWaterModel | `created_at` unix | `(id, created_at)` |
| `me_user_log` | UserLogModel | `created_at` unix | `(id, created_at)` |
| `me_user_login` | UserLoginModel | `created_at` unix | `(id, created_at)` |
| `me_user_virtual_log` | UserVirtualLogModel | `created_at` unix | `(id, created_at)` |
| `sy_admin_logs` | AdminLogModel | `created_at` timestamp | `(id, created_at)` |
| `act_turntable_log` | TurntableLogModel | `created_at` unix | `(id, created_at)` |

### 3.4 不建议分区

- `me_user_ip`：按 IP 维度查询，非时间序列
- 小表 / 配置表：分区收益为负

---

## 四、分区使用说明

### 模型声明

```php
use app\model\Concerns\PartitionedLogTable;

class UserWaterModel extends Model
{
    use PartitionedLogTable;

    public static function tableSchema(): array
    {
        return [
            // ...
            'partition' => self::monthlyPartition(), // 默认 created_at unix
        ];
    }
}
```

### 应用分区

```bash
# 全部已声明 partition.enabled 的表
php scripts/apply_table_partitions.php

# 单表
php scripts/apply_table_partitions.php UserWaterModel
```

### 维护

- 每月初执行一次 `apply_table_partitions.php` 可扩展 `pmax` 前分区（脚本含未来 24 个月 + 过去 3 个月）
- 归档：直接 `ALTER TABLE ... DROP PARTITION p202401` 删除整月冷数据
- **查询务必带 `created_at` 范围**，才能触发分区裁剪
- `php webman model` 不会破坏已有分区，但也不会自动创建分区

---

## 五、已知命名不一致（保留，文档记录）

| 问题 | 现状 | 建议（新表） |
|------|------|-------------|
| 单复数混用 | `co_video` vs `co_articles` | 内容表统一复数 |
| 类名与表名 | `CustomerServiceLinkModel` → `co_customer_services` | 表名跟业务语义 |
| 支付前缀 | 目录 `pay/`，表 `pa_` | 保持 `pa_` |
| 运营前缀 | 目录 `ops/`，表 `toc_` | 保持 `toc_` |
| 流水命名 | `me_user_water`（流水） | 保持，与参考项目一致 |

---

## 六、待规范化（需单独迁移，本次未改表名/字段名）

以下改动影响 API / 全栈，需专门迁移窗口：

1. `co_video` → `co_videos`（可选）
2. 遗留 `ad_*` / `act_*` 统一改为 Laravel timestamps（工作量大）
3. `mg_recharge_orders` 孤儿表（模型中无定义）→ 确认后删除或合并到 `pa_recharge_order`

---

## 七、脚本工具

| 脚本 | 用途 |
|------|------|
| `scripts/audit_field_naming.php` | 扫描字段命名不规范，输出 [FIELD_NAMING_ISSUES.md](./FIELD_NAMING_ISSUES.md) |
| `scripts/apply_field_renames.php` | 按 `config/field_renames.php` 批量重命名字段 |
| `scripts/list_table_stats.php` | 查看表行数、大小、是否已分区 |
| `scripts/apply_table_partitions.php` | 应用模型中声明的分区 |
| `scripts/list_foreign_keys.php` | 查看外键（已全库删除） |
| `scripts/drop_foreign_keys.php` | 删除外键 |
| `scripts/encrypt_php.php` | 加密 PHP 源码（见 [deploy/ENCRYPT_PHAR.md](../deploy/ENCRYPT_PHAR.md)） |
| `scripts/build_encrypted_phar.php` | 加密并打 PHAR 发布包 |
| `scripts/check_loader.php` | 服务器自检 `financeos_loader` 扩展 |
| `scripts/member_api_smoke.php` | 会员 API 冒烟测试 |
