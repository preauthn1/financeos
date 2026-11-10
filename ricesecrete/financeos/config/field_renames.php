<?php

declare(strict_types=1);

/**
 * 字段批量重命名映射（表 => [旧字段 => 新字段]）。
 * 已执行迁移后本文件仅作文档；重复执行 apply 会 SKIP 已改名列。
 */
return [
    'pa_recharge_order' => [
        'order_no'       => 'order_no',
        'order_source'           => 'order_source',
        'pay_platform_id'         => 'pay_platform_id',
        'transaction_hash'           => 'transaction_hash',
        'chain_tx_id'           => 'chain_tx_id',
        'voucher_url'            => 'voucher_url',
        'paid_at'       => 'paid_at',
        'succeeded_at'   => 'succeeded_at',
        'coin_amount'     => 'coin_amount',
        'original_amount' => 'original_amount',
    ],
    'pa_withdraw_order' => [
        'order_no'        => 'order_no',
        'withdraw_amount'  => 'withdraw_amount',
        'original_amount'  => 'original_amount',
        'payout_amount'   => 'payout_amount',
        'paid_at'        => 'paid_at',
        'checked_at'      => 'checked_at',
    ],
    'pa_platform' => [
        'exchange_updated_at' => 'exchange_updated_at',
        'max_withdraw_amount'   => 'max_withdraw_amount',
        'min_recharge_amount'   => 'min_recharge_amount',
        'min_withdraw_amount'   => 'min_withdraw_amount',
        'image_url'                => 'image_url',
    ],
    'me_user' => [
        'is_transfer_enabled'   => 'is_transfer_enabled',
        'is_invite_code_enabled' => 'is_invite_code_enabled',
    ],
    'me_user_real_name_auth' => [
        'admin_id' => 'admin_id',
    ],
    'me_user_message' => [
        'is_popup' => 'is_popup',
    ],
    'me_user_ad_countdown_config' => [
        'can_click_on_no'  => 'can_click_on_no',
        'can_click_on_yes' => 'can_click_on_yes',
    ],
    'ad_product_order' => [
        'bought_at'             => 'bought_at',
        'ended_at'             => 'ended_at',
        'matched_at'           => 'matched_at',
        'settled_at'      => 'settled_at',
        'started_at'           => 'started_at',
        'invest_amount'                => 'invest_amount',
        'overdue_amount'        => 'overdue_amount',
        'profit_amount'         => 'profit_amount',
        'product_name' => 'product_name',
        'plan_id' => 'ad_plan_days_config_id',
    ],
    'ad_config' => [
        'level_at'              => 'level_at',
        'password_expired_at'    => 'password_expired_at',
        'voucher_url'                     => 'image_url',
        'invite_num_amount'        => 'invite_num_amount',
        'withdraw_trx_min_amount'  => 'withdraw_trx_min_amount',
        'withdraw_usdt_min_amount' => 'withdraw_usdt_min_amount',
    ],
    'ad_advertis_space' => [
        'cpm_cost_amount' => 'cpm_cost_amount',
        'turnover_amount' => 'turnover_amount',
    ],
    'act_activity' => [
        'started_at'      => 'started_at',
        'ended_at'        => 'ended_at',
        'expired_at' => 'expired_at',
        'user_amount'      => 'user_amount',
    ],
    'act_activity_record' => [
        'returned_at' => 'returned_at',
    ],
    'act_pdd_turntable' => [
        'started_at' => 'started_at',
        'ended_at'   => 'ended_at',
    ],
    'act_turntable_log' => [
        'invest_amount' => 'amount',
    ],
    'act_turntable_prize' => [
        'voucher_url' => 'image_url',
    ],
    'iv_product' => [
        'started_at'  => 'started_at',
        'ended_at'    => 'ended_at',
        'rebated_at' => 'rebated_at',
        'give_amount'  => 'give_amount',
    ],
    'iv_product_order' => [
        'started_at'      => 'started_at',
        'ended_at'        => 'ended_at',
        'settled_at' => 'settled_at',
        'profit_amount'    => 'profit_amount',
    ],
    'me_level_team' => [
        'overdue_at' => 'overdue_at',
    ],
    'me_level_team_invest_time' => [
        'started_at' => 'started_at',
        'ended_at'   => 'ended_at',
    ],
    'me_team_active_reward_v2' => [
        'received_at' => 'received_at',
    ],
    'toc_operation_center' => [
        'authenticated_at' => 'authenticated_at',
    ],
    'co_banners' => [
        'image_url' => 'image_url',
    ],
    'co_video' => [
        'image_url' => 'image_url',
    ],
    'co_customer_services' => [
        'image_url' => 'image_url',
    ],
];
