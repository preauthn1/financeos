<?php

declare(strict_types=1);

$onOff = static fn (string $on = '开启', string $off = '关闭') => [
    ['value' => '1', 'label' => $on],
    ['value' => '0', 'label' => $off],
];

$yesNo = static fn () => [
    ['value' => '1', 'label' => '是'],
    ['value' => '0', 'label' => '否'],
];

return [
    'tabs' => [
        [
            'key' => 'base',
            'label' => '基本功能',
            'fields' => [
                ['key' => 'sys_currency_type', 'label' => '系统货币类型', 'type' => 'radio', 'options' => [
                    ['value' => '1', 'label' => '虚拟币'],
                    ['value' => '2', 'label' => '货币'],
                ], 'default' => '1', 'help' => 'sys_currency_type：系统货币类型。虚拟币单位默认 USDT；选择货币时可自定义单位与符号。'],
                ['key' => 'fiat_currency_unit', 'label' => '货币单位', 'type' => 'text', 'default' => 'USDT', 'show_when' => ['sys_currency_type' => '2'], 'help' => 'fiat_currency_unit：货币单位（如 USD / CNY）。'],
                ['key' => 'fiat_currency_symbol', 'label' => '货币符号', 'type' => 'text', 'default' => '$', 'show_when' => ['sys_currency_type' => '2'], 'help' => 'fiat_currency_symbol：货币符号（如 $ / ¥）。'],
                ['key' => 'fiat_currency_rate', 'label' => '汇率', 'type' => 'text', 'default' => '0.1449', 'show_when' => ['sys_currency_type' => '2'], 'help' => 'fiat_currency_rate：1 平台币 = 该汇率对应法币金额，用于前端换算展示。'],
                ['key' => 'google_secret_status', 'label' => '用户端是否开启谷歌验证器', 'type' => 'radio', 'options' => $onOff(), 'default' => '0', 'help' => 'google_secret_status：开启后，用户端相关安全操作需绑定/校验谷歌验证器。'],
                ['key' => 'is_brokerage_to_basic', 'label' => '佣金账户是否可转到基础账户', 'type' => 'radio', 'options' => $onOff(), 'default' => '0', 'help' => 'is_brokerage_to_basic：开启后，用户可将佣金账户余额转入基础账户。'],
                ['key' => 'invest_status', 'label' => '是否开启投资', 'type' => 'radio', 'options' => $onOff(), 'default' => '0', 'help' => 'invest_status：AI/投资产品入口总开关。关闭后用户端不可进入投资相关功能。'],
                ['key' => 'sys_data_time', 'label' => '网站使用时区', 'type' => 'select', 'options_ref' => 'timezones', 'default' => 'Asia/Hong_Kong', 'help' => 'sys_data_time：网站当前使用的时区，统计、订单、报表时间均按此时区计算，请按运营地区调整。'],
                ['key' => 'recharge_voice_file', 'label' => '充值语音文件', 'type' => 'upload', 'accept' => 'audio', 'default' => '', 'help' => 'recharge_voice_file：后台收到新充值订单时播放的提示音文件。'],
                ['key' => 'withdraw_voice_file', 'label' => '提现语音文件', 'type' => 'upload', 'accept' => 'audio', 'default' => '', 'help' => 'withdraw_voice_file：后台收到新提现订单时播放的提示音文件。'],
                ['key' => 'official_website_url', 'label' => '官网地址', 'type' => 'text', 'default' => '', 'help' => 'official_website_url：对外展示的官网地址。'],
                ['key' => 'api_sign_secret', 'label' => '广告撮合签名密钥', 'type' => 'password', 'default' => 'akz8VEU!kru6kgk9dhp', 'help' => 'api_sign_secret：用于 /api/finance_product/match、overdue_match 请求验签；修改后客户端签名逻辑必须同步，否则撮合会失败。'],
            ],
        ],
        [
            'key' => 'client',
            'label' => '用户端配置',
            'fields' => [
                ['key' => 'admin_icon', 'label' => '网站 icon', 'type' => 'upload', 'accept' => 'image', 'default' => '', 'help' => 'admin_icon：浏览器标签页图标（favicon）。'],
                ['key' => 'logo', 'label' => '网站 logo', 'type' => 'upload', 'accept' => 'image', 'default' => '', 'help' => 'logo：用户端顶部/登录页等位置展示的站点 Logo。'],
                ['key' => 'name', 'label' => '应用名称', 'type' => 'text', 'default' => '', 'help' => 'name：应用名称，展示在用户端标题等位置。'],
                ['key' => 'ios_url', 'label' => 'IOS-APP 文件', 'type' => 'upload', 'accept' => 'file', 'default' => '', 'help' => 'ios_url：iOS App 安装包/描述文件下载地址。'],
                ['key' => 'android_url', 'label' => 'Android-APP 文件', 'type' => 'upload', 'accept' => 'file', 'default' => '', 'help' => 'android_url：Android App 安装包下载地址。'],
                ['key' => 'mt_android_url', 'label' => 'AdEx-Android-APP 文件', 'type' => 'upload', 'accept' => 'file', 'default' => '', 'help' => 'mt_android_url：AdEx 渠道 Android 安装包下载地址。'],
                ['key' => 'mt_ios_url', 'label' => 'AdEx-IOS-APP 文件', 'type' => 'upload', 'accept' => 'file', 'default' => '', 'help' => 'mt_ios_url：AdEx 渠道 iOS 安装包/描述文件下载地址。'],
                ['key' => 'app_version', 'label' => 'APP 版本号', 'type' => 'text', 'default' => '', 'help' => 'app_version：应用版本号，用于用户端版本展示与更新判断。'],
                ['key' => 'index_video_is_show', 'label' => '首页视频是否显示', 'type' => 'radio', 'options' => $onOff(), 'default' => '0', 'help' => 'index_video_is_show：首页是否展示宣传视频。'],
                ['key' => 'index_video_url', 'label' => '首页视频地址', 'type' => 'upload', 'accept' => 'video', 'default' => '', 'help' => 'index_video_url：首页展示的视频文件地址。'],
                ['key' => 'is_show_cate', 'label' => '是否显示分类', 'type' => 'radio', 'options' => $onOff(), 'default' => '0', 'help' => 'is_show_cate：首页是否显示分类入口。'],
                ['key' => 'is_show_fake_data', 'label' => '是否显示假数据', 'type' => 'radio', 'options' => $onOff(), 'default' => '0', 'help' => 'is_show_fake_data：首页是否展示模拟/假数据（造势用）。'],
                ['key' => 'index_show_data', 'label' => '首页展示数据', 'type' => 'radio', 'options' => [
                    ['value' => 'vip', 'label' => 'vip'],
                    ['value' => 'goods', 'label' => '商品'],
                ], 'default' => 'vip', 'help' => 'index_show_data：大厅主内容展示类型：VIP 或商品。'],
                ['key' => 'index_vip_show_type', 'label' => '首页 VIP 展示类型', 'type' => 'radio', 'options' => [
                    ['value' => 'earnings_amount', 'label' => 'vip收益金额'],
                    ['value' => 'unlock_amount', 'label' => 'vip解锁金额'],
                ], 'default' => 'earnings_amount', 'help' => 'index_vip_show_type：大厅 VIP 卡片展示收益金额或解锁金额。'],
                ['key' => 'level_income_type', 'label' => '等级收益展示', 'type' => 'radio', 'options' => [
                    ['value' => '1', 'label' => '单个任务收益'],
                    ['value' => '2', 'label' => '显示总收益'],
                ], 'default' => '1', 'help' => 'level_income_type：等级收益展示方式：单次任务收益或总收益。'],
                ['key' => 'system_notice_frequency', 'label' => '系统公告频率', 'type' => 'radio', 'options' => [
                    ['value' => 'once', 'label' => '仅一次'],
                    ['value' => 'login', 'label' => '每次登录'],
                ], 'default' => 'login', 'help' => 'system_notice_frequency：系统公告弹窗频率：仅弹一次，或每次登录都弹。'],
                ['key' => 'index_force_notice', 'label' => '首页强制公告', 'type' => 'radio', 'options' => [
                    ['value' => 'once', 'label' => '仅一次'],
                    ['value' => 'login', 'label' => '每次登录'],
                ], 'default' => 'login', 'help' => 'index_force_notice：首页强制弹窗公告频率。'],
                ['key' => 'system_default_lang', 'label' => '系统默认语言', 'type' => 'select', 'options_ref' => 'languages', 'default' => 'zh-cn', 'help' => 'system_default_lang：用户端默认语言（未选择语言时使用）。'],
                ['key' => 'system_default_phone_code', 'label' => '默认手机区号', 'type' => 'select', 'options_ref' => 'phone_codes', 'default' => '+86', 'help' => 'system_default_phone_code：注册/登录手机号默认国际区号。'],
                ['key' => 'index_is_login', 'label' => '首页是否需要登录', 'type' => 'radio', 'options' => $yesNo(), 'default' => '0', 'help' => 'index_is_login：开启后访问首页必须先登录。'],
                ['key' => 'customer_logo_status', 'label' => '客服 logo 开关', 'type' => 'radio', 'options' => $onOff(), 'default' => '0', 'help' => 'customer_logo_status：是否显示悬浮客服入口。'],
                ['key' => 'customer_logo', 'label' => '客服 logo', 'type' => 'upload', 'accept' => 'image', 'default' => '', 'help' => 'customer_logo：悬浮客服图标图片。'],
                ['key' => 'invest_rebate_type', 'label' => '投资收益展示', 'type' => 'radio', 'options' => [
                    ['value' => '1', 'label' => '显示收益率'],
                    ['value' => '2', 'label' => '显示收益金额'],
                ], 'default' => '1', 'help' => 'invest_rebate_type：投资产品收益展示类型：收益率或收益金额。'],
                ['key' => 'decimal_places', 'label' => '小数位数', 'type' => 'number', 'default' => 2, 'min' => 1, 'max' => 8, 'help' => 'decimal_places：用户端货币金额小数显示位数（1~8）。'],
                ['key' => 'is_distinguish_admin_add_money', 'label' => '区分后台加款', 'type' => 'radio', 'options' => $yesNo(), 'default' => '0', 'help' => 'is_distinguish_admin_add_money：资产明细是否区分后台补单与用户充值。'],
                ['key' => 'salesmartly_src', 'label' => 'SaleSmartly 脚本', 'type' => 'textarea', 'default' => '', 'help' => 'salesmartly_src：SaleSmartly 在线客服聊天插件脚本/src 链接。'],
            ],
        ],
        [
            'key' => 'withdraw',
            'label' => '出款配置',
            'fields' => [
                ['key' => 'withdraw_open', 'label' => '提现开关', 'type' => 'radio', 'options' => $onOff(), 'default' => '1', 'help' => 'withdraw_open：提现总开关。关闭后用户无法发起提现。'],
                ['key' => 'is_repeat_withdraw_address', 'label' => '是否允许重复提现地址', 'type' => 'radio', 'options' => $onOff(), 'default' => '0', 'help' => 'is_repeat_withdraw_address：不同用户是否允许绑定相同提现地址。'],
                ['key' => 'first_withdraw_input_contact', 'label' => '首次提现填写联系方式', 'type' => 'radio', 'options' => $onOff(), 'default' => '0', 'help' => 'first_withdraw_input_contact：第一次提现是否要求填写联系方式。'],
                ['key' => 'first_withdraw_is_min', 'label' => '首次提现最低金额限制', 'type' => 'radio', 'options' => $onOff(), 'default' => '0', 'help' => 'first_withdraw_is_min：第一次提现是否单独设置最低金额。'],
                ['key' => 'first_withdraw_min_money', 'label' => '首次提现最低金额', 'type' => 'number', 'default' => 0, 'min' => 0, 'help' => 'first_withdraw_min_money：第一次提现最低提现金额（需开启「首次提现最低金额限制」后生效）。'],
                ['key' => 'withdraw_check_hour', 'label' => '提现审核小时数', 'type' => 'number', 'default' => 0, 'min' => 0, 'step' => 0.01, 'help' => 'withdraw_check_hour：出款审核时间（小时），审核通过后才能真正出款。'],
                ['key' => 'day_tx_count', 'label' => '每日提现次数', 'type' => 'number', 'default' => 0, 'min' => 0, 'help' => 'day_tx_count：每用户每日可提现次数；填 0 表示不限制。'],
                ['key' => 'withdraw_no_auth_times', 'label' => '未实名可提现次数', 'type' => 'number', 'default' => 0, 'min' => 0, 'help' => 'withdraw_no_auth_times：免审核提现次数；填 0 表示所有提现都需要审核。'],
                ['key' => 'withdraw_need_kyc', 'label' => '提现需要 KYC', 'type' => 'radio', 'options' => $onOff(), 'default' => '0', 'help' => 'withdraw_need_kyc：提现是否必须完成实名认证（KYC）。'],
                ['key' => 'vip0_is_withdraw', 'label' => 'VIP0 是否可提现', 'type' => 'radio', 'options' => $onOff(), 'default' => '0', 'help' => 'vip0_is_withdraw：VIP0（未升级）用户是否允许提现。'],
                ['key' => 'withdraw_water_limit', 'label' => '提现流水限制', 'type' => 'number', 'default' => 0, 'min' => 0, 'help' => 'withdraw_water_limit：提现流水要求，按百分比填写（如 10% 填 10）；0 表示不限制。'],
                ['key' => 'user_water_limit_tips', 'label' => '流水不足提示语', 'type' => 'text', 'default' => '', 'help' => 'user_water_limit_tips：流水不足时给用户的提示文案。'],
                ['key' => 'auto_payment', 'label' => '自动出款', 'type' => 'radio', 'options' => $onOff(), 'default' => '0', 'help' => 'auto_payment：是否开启自动出款（满足条件时无需人工点出款）。'],
                ['key' => 'auto_payment_safety_value', 'label' => '自动出款安全值', 'type' => 'number', 'default' => 0, 'min' => 0, 'help' => 'auto_payment_safety_value：自动出款金额安全阈值；超过该金额需后台人工审核出款。'],
                ['key' => 'min_withdraw_config', 'label' => '最低提现配置', 'type' => 'radio', 'options' => [
                    ['value' => 'global', 'label' => '全局'],
                    ['value' => 'local', 'label' => '局部'],
                ], 'default' => 'global', 'help' => 'min_withdraw_config：最小提现金额来源。全局=支付通道配置；局部=VIP/等级配置。'],
                ['key' => 'withdrawal_100_multiple', 'label' => '提现 100 倍数', 'type' => 'radio', 'options' => $onOff(), 'default' => '0', 'help' => 'withdrawal_100_multiple：开启后提现金额必须为 100 的整数倍。'],
                ['key' => 'today_recharge_withdraw_rate_warning', 'label' => '今日充提比预警', 'type' => 'text', 'default' => '', 'help' => 'today_recharge_withdraw_rate_warning：当日有充值时，提现超过当日充值该百分比则预警；按百分比填写（如 10% 填 10）。'],
                ['key' => 'free_withdraw_fee_num', 'label' => '免手续费次数', 'type' => 'number', 'default' => 0, 'min' => 0, 'help' => 'free_withdraw_fee_num：单日免手续费提现笔数。'],
                ['key' => 'withdraw_fee_min', 'label' => '手续费最小值', 'type' => 'number', 'default' => 0, 'min' => 0, 'help' => 'withdraw_fee_min：单笔提现手续费下限。'],
                ['key' => 'withdraw_fee_max', 'label' => '手续费最大值', 'type' => 'number', 'default' => 0, 'min' => 0, 'help' => 'withdraw_fee_max：单笔提现手续费上限。'],
                ['key' => 'withdraw_rate', 'label' => '提现手续费比例', 'type' => 'number', 'default' => 0, 'min' => 0, 'help' => 'withdraw_rate：提现手续费百分比（如 5 表示 5%）。'],
                ['key' => 'withdraw_rate_method', 'label' => '手续费扣除方式', 'type' => 'radio', 'options' => [
                    ['value' => '0', 'label' => '从余额中扣除'],
                    ['value' => '1', 'label' => '从提现金额中扣除'],
                ], 'default' => '0', 'help' => 'withdraw_rate_method：手续费从账户余额额外扣，还是从本次提现金额中扣。'],
                ['key' => 'withdraw_level_config', 'label' => '提现等级配置', 'type' => 'json', 'default' => '{"deposit_upgrade_enabled":0,"levels":[{"level":0,"name":"普通","direct_count":0,"fee_rate":5},{"level":1,"name":"中级","direct_count":5,"fee_rate":3,"deposit":1000,"deposit_days":7},{"level":2,"name":"高级","direct_count":20,"fee_rate":1,"deposit":2000,"deposit_days":10}]}', 'help' => 'withdraw_level_config：提现等级 JSON。含 deposit_upgrade_enabled 与 levels（直推人数、手续费率、押金等）。'],
                ['key' => 'withdraw_large_fee', 'label' => '大额固定手续费', 'type' => 'json', 'default' => '{"threshold":10000,"rate":1}', 'help' => 'withdraw_large_fee：大额提现手续费 JSON。threshold=门槛金额，rate=费率/固定规则。'],
                ['key' => 'sign_in_reward', 'label' => '签到奖励(入佣金池)', 'type' => 'number', 'default' => 1, 'min' => 0, 'help' => 'sign_in_reward：每日签到奖励金额，发放至佣金池。'],
            ],
        ],
        [
            'key' => 'register',
            'label' => '注册登录',
            'sections' => [
                ['label' => '注册配置'],
                ['label' => '邀请配置'],
                ['label' => '登录配置'],
                ['label' => '实名认证'],
            ],
            'fields' => [
                ['key' => 'account_rule', 'label' => '注册账号规则', 'type' => 'radio', 'section' => 0, 'options' => [
                    ['value' => '0', 'label' => '手机号或邮箱'],
                    ['value' => '1', 'label' => '只能邮箱'],
                    ['value' => '2', 'label' => '只能手机'],
                ], 'default' => '0', 'help' => 'account_rule：注册账号类型限制：手机/邮箱均可、仅邮箱或仅手机。'],
                ['key' => 'is_show_user_protocol', 'label' => '用户协议是否显示', 'type' => 'radio', 'section' => 0, 'options' => [
                    ['value' => '1', 'label' => '显示'],
                    ['value' => '0', 'label' => '隐藏'],
                ], 'default' => '1', 'help' => 'is_show_user_protocol：注册页是否展示用户协议与隐私协议。'],
                ['key' => 'register_give_money', 'label' => '注册赠送金额', 'type' => 'number', 'section' => 0, 'default' => 0, 'min' => 0, 'help' => 'register_give_money：新用户注册成功后赠送金额；0 表示不赠送。'],
                ['key' => 'reg_ip_limit', 'label' => '同 IP 注册限制', 'type' => 'number', 'section' => 0, 'default' => 0, 'min' => 0, 'help' => 'reg_ip_limit：同一 IP 可注册账号个数上限；0 表示不限制。'],
                ['key' => 'is_repeat_password', 'label' => '重复密码确认', 'type' => 'radio', 'section' => 0, 'options' => $onOff(), 'default' => '0', 'help' => 'is_repeat_password：注册时是否要求重复确认登录密码与安全密码。'],
                ['key' => 'is_verify_email_code', 'label' => '邮箱验证码', 'type' => 'radio', 'section' => 0, 'options' => $onOff(), 'default' => '0', 'help' => 'is_verify_email_code：邮箱注册时是否必须验证邮箱验证码。'],
                ['key' => 'is_verify_phone_code', 'label' => '手机验证码', 'type' => 'radio', 'section' => 0, 'options' => $onOff(), 'default' => '0', 'help' => 'is_verify_phone_code：手机注册/登录时是否必须验证短信验证码。'],
                ['key' => 'verify_code_ttl', 'label' => '验证码有效时间(秒)', 'type' => 'number', 'section' => 0, 'default' => 180, 'min' => 60, 'help' => 'verify_code_ttl：验证码有效时间，单位秒。'],
                ['key' => 'open_region_limit', 'label' => '开启地区访问限制', 'type' => 'text', 'section' => 0, 'default' => '0', 'help' => 'open_region_limit：填 1 开启地区访问限制，填 0 关闭。'],
                ['key' => 'region_limit_field', 'label' => '限制字段', 'type' => 'select', 'section' => 0, 'options_ref' => 'region_limit_fields', 'default' => 'countryCode', 'help' => 'region_limit_field：按 IP 归属的哪一字段做地区匹配（国家代码/国家名/省/城市）。'],
                ['key' => 'allow_region_limit', 'label' => '允许地区名称', 'type' => 'text', 'section' => 0, 'default' => '', 'help' => 'allow_region_limit：白名单地区，多个用英文逗号分隔；与禁止名单一般只设其一。'],
                ['key' => 'deny_region_limit', 'label' => '禁止地区名称', 'type' => 'text', 'section' => 0, 'default' => '', 'help' => 'deny_region_limit：黑名单地区，多个用英文逗号分隔；禁止权重高于允许，且与允许一般只设其一。'],
                ['key' => 'deny_region_limit_tips', 'label' => '禁止地区提示语', 'type' => 'text', 'section' => 0, 'default' => '', 'help' => 'deny_region_limit_tips：命中禁止地区时展示给用户的提示文案。'],
                ['key' => 'invite_code_required', 'label' => '邀请码是否必填', 'type' => 'radio', 'section' => 1, 'options' => $onOff(), 'default' => '0', 'help' => 'invite_code_required：注册时邀请码是否必填。'],
                ['key' => 'recharge_num_valid_user', 'label' => '有效用户充值金额', 'type' => 'number', 'section' => 1, 'default' => 0, 'min' => 0, 'help' => 'recharge_num_valid_user：累计充值达到该金额即计为有效用户（邀请/任务统计用）。'],
                ['key' => 'agent_line_roots', 'label' => '代理线根用户ID', 'type' => 'text', 'section' => 1, 'default' => '{"bai_zhi":0,"nuo_nuo":0,"da_shun":0}', 'help' => 'agent_line_roots：代理线根用户 ID 的 JSON，如 {"bai_zhi":1,"nuo_nuo":2,"da_shun":3}，用于报表/代理线归属。'],
                ['key' => 'error_to_ban', 'label' => '封停账号错误次数', 'type' => 'number', 'section' => 2, 'default' => 0, 'min' => 0, 'help' => 'error_to_ban：连续输错密码达到该次数后封停登录；0 表示不因错误次数封停。'],
                ['key' => 'auto_real_name_auth', 'label' => '自动审核实名认证', 'type' => 'radio', 'section' => 3, 'options' => $onOff(), 'default' => '0', 'help' => 'auto_real_name_auth：开启后用户提交实名资料自动通过，无需人工审核。'],
            ],
        ],
        [
            'key' => 'recharge',
            'label' => '充值设置',
            'fields' => [
                ['key' => 'is_open_first_charge_rebate', 'label' => '首充返利开关', 'type' => 'radio', 'options' => $onOff(), 'default' => '0', 'help' => 'is_open_first_charge_rebate：首充送款/返利总开关。'],
                ['key' => 'first_charge_rebate_min', 'label' => '首充返利最小金额', 'type' => 'text', 'default' => '0', 'help' => 'first_charge_rebate_min：首充金额 ≥ 该值才返利；0 表示不限制最低金额。'],
                ['key' => 'first_charge_rebate_max', 'label' => '首充返利最大金额', 'type' => 'text', 'default' => '0', 'help' => 'first_charge_rebate_max：首充金额 ≤ 该值才返利（最高要求）。'],
                ['key' => 'first_charge_rebate_percent', 'label' => '首充返利比例', 'type' => 'text', 'default' => '0', 'help' => 'first_charge_rebate_percent：首充送款比例，按百分比填写（如 10% 填 10）。'],
                ['key' => 'first_charge_rebate_cap', 'label' => '首充返利上限', 'type' => 'text', 'default' => '0', 'help' => 'first_charge_rebate_cap：单次首充返利封顶金额；0 表示不封顶。'],
            ],
        ],
        [
            'key' => 'task',
            'label' => '任务配置',
            'fields' => [
                ['key' => 'is_watch_video', 'label' => '是否观看视频才能做任务', 'type' => 'radio', 'options' => $yesNo(), 'default' => '0', 'help' => 'is_watch_video：开启后用户需先观看指定视频才能开始做任务。'],
                ['key' => 'level_upgrade_type', 'label' => 'VIP 解锁方式', 'type' => 'radio', 'options' => [
                    ['value' => '1', 'label' => '根据基础余额直接升级不扣钱'],
                    ['value' => '2', 'label' => '扣除基础余额，并返还之前等级解锁金额到佣金'],
                ], 'default' => '1', 'help' => 'level_upgrade_type：VIP 解锁方式。1=余额达标直接升级不扣款；2=扣除基础余额，并把之前等级解锁金额返还到佣金账户。'],
                ['key' => 'task_reset_type', 'label' => '任务重置方式', 'type' => 'radio', 'options' => [
                    ['value' => '1', 'label' => '绝对24小时'],
                    ['value' => '2', 'label' => '某个时间重置'],
                    ['value' => '3', 'label' => '一天多场'],
                ], 'default' => '3', 'help' => 'task_reset_type：1=绝对24小时（做完后隔24小时再做）；2=固定时刻全员重置；3=一天多场（按广告位时段）。'],
                ['key' => 'task_reset_time', 'label' => '任务重置时间', 'type' => 'text', 'default' => '00:00', 'show_when' => ['task_reset_type' => '2'], 'placeholder' => 'H点m分', 'help' => 'task_reset_time：当重置方式为「某个时间重置」时生效，格式如 00:00 或 H点m分。'],
                ['key' => 'is_task_count', 'label' => '做任务限制', 'type' => 'radio', 'options' => $onOff(), 'default' => '0', 'help' => 'is_task_count：开启后，需用户升级 VIP 或邀请有效用户，才能获得做任务机会。'],
            ],
        ],
        [
            'key' => 'share',
            'label' => '分享奖励',
            'fields' => [
                ['key' => 'is_auto_check', 'label' => '分享审核方式', 'type' => 'radio', 'options' => [
                    ['value' => '1', 'label' => '自动'],
                    ['value' => '2', 'label' => '手动审核'],
                ], 'default' => '1', 'help' => 'is_auto_check：分享任务审核方式。1=自动通过；2=需后台手动审核。'],
            ],
        ],
        [
            'key' => 'credit_score',
            'label' => '信用分',
            'fields' => [
                ['key' => 'finance_product_no_can_click', 'label' => '未达标是否可点击', 'type' => 'radio', 'options' => [
                    ['value' => '1', 'label' => '可以'],
                    ['value' => '0', 'label' => '不可以'],
                ], 'default' => '0', 'help' => 'finance_product_no_can_click：超额匹配弹窗中「否」按钮是否可点击。'],
                ['key' => 'finance_product_yes_can_click', 'label' => '已达标是否可点击', 'type' => 'radio', 'options' => [
                    ['value' => '1', 'label' => '可以'],
                    ['value' => '0', 'label' => '不可以'],
                ], 'default' => '1', 'help' => 'finance_product_yes_can_click：超额匹配弹窗中「是」按钮是否可点击。'],
                ['key' => 'finance_product_dialog_count_down', 'label' => '弹窗倒计时', 'type' => 'text', 'default' => '', 'help' => 'finance_product_dialog_count_down：超额匹配弹窗倒计时秒数。'],
                ['key' => 'finance_product_dialog_btn_cancel', 'label' => '弹窗取消按钮文案', 'type' => 'text', 'default' => '', 'help' => 'finance_product_dialog_btn_cancel：【否】按钮 loading 时长（秒）；历史字段名沿用。'],
                ['key' => 'finance_product_button_count_down', 'label' => '按钮倒计时', 'type' => 'text', 'default' => '', 'help' => 'finance_product_button_count_down：【是】按钮 loading 时长（秒）。'],
                ['key' => 'credit_score_trade_num', 'label' => '交易次数信用分', 'type' => 'text', 'default' => '', 'help' => 'credit_score_trade_num：格式 总分_多少交易量加一分（例如 30_200）。'],
                ['key' => 'credit_score_active_num', 'label' => '活跃次数信用分', 'type' => 'text', 'default' => '', 'help' => 'credit_score_active_num：格式 总分:分值范围_m人加n分:...（例如 40:0-20_1_10:21-30_2_5:31-40_3_6）。'],
                ['key' => 'credit_score_new_num', 'label' => '新用户信用分', 'type' => 'text', 'default' => '', 'help' => 'credit_score_new_num：格式 总分:前n人_每人加m分（例如 30:5_3）。'],
                ['key' => 'credit_score_overdue', 'label' => '逾期信用分', 'type' => 'text', 'default' => '', 'help' => 'credit_score_overdue：格式 时长(小时):扣分值_时长:扣分值（例如 24:5_36:20），最多四组。'],
                ['key' => 'credit_score_times', 'label' => '信用分倍数规则', 'type' => 'text', 'default' => '1:0-100', 'help' => 'credit_score_times：格式 倍数:最小分-最大分_倍数:最小分-最大分（例如 1:50-59_2:60-69），用于撮合金额预支倍数。'],
            ],
        ],
        [
            'key' => 'repayment_reminder',
            'label' => '平台还款提醒',
            'fields' => [
                ['key' => 'repayment_reminder_content', 'label' => '提醒内容', 'type' => 'text', 'default' => '', 'help' => 'repayment_reminder_content：用户有欠款时的弹窗提醒文案。'],
                ['key' => 'repayment_reminder_status', 'label' => '是否开启', 'type' => 'radio', 'options' => $yesNo(), 'default' => '0', 'help' => 'repayment_reminder_status：是否开启欠款弹窗提醒。'],
            ],
        ],
        [
            'key' => 'domain',
            'label' => '入口/落地域名配置',
            'fields' => [
                ['key' => 'app_entry_domain', 'label' => 'APP 入口域名', 'type' => 'textarea', 'default' => '', 'rows' => 1, 'help' => 'app_entry_domain：APP 专用入口域名；每行一个，格式 入口|落地（无需 https://），如 baidu.com|google.com。'],
                ['key' => 'entry_domain', 'label' => '落地域名列表', 'type' => 'textarea', 'default' => '', 'rows' => 15, 'help' => 'entry_domain：Web 入口/落地域名列表；每行一个，格式 入口|落地（无需 https://），访问入口域名会跳转到落地地址。'],
            ],
        ],
        [
            'key' => 'storage',
            'label' => '存储配置',
            'fields' => [
                ['key' => 's3_config', 'label' => 'S3 对象存储', 'type' => 's3_config', 'default' => [
                    'credentials_key' => '',
                    'credentials_secret' => '',
                    'region' => 'ap-east-1',
                    'bucket' => '',
                    'url' => '',
                    'proxy' => null,
                    'presign_expires' => 900,
                ], 'help' => 's3_config：对象存储（S3 兼容）凭证与桶配置，用于上传图片/音视频/安装包等文件；presign_expires 为预签名有效秒数。'],
            ],
        ],
    ],
];
