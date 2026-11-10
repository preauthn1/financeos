<?php

declare(strict_types=1);

namespace app\model\member;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserModel extends Model
{
    use DefinesTableSchema;
    use SoftDeletes;

    protected $table = 'me_user';

    protected $fillable = [
        'username', 'nickname', 'real_name', 'mobile', 'phone_code', 'email',
        'password', 'pay_password', 'avatar', 'lang',
        'balance', 'recharge_balance', 'brokerage_balance', 'frozen_balance',
        'trial_balance', 'points',
        'total_recharge', 'total_withdraw', 'total_earnings', 'total_brokerage',
        'level_id', 'leader1', 'leader2', 'leader3', 'leader4', 'leader5', 'leader6', 'leader7',
        'tree_id', 'invite_code', 'is_invite_code_enabled',
        'status', 'is_activated', 'is_virtual', 'is_proxy', 'kyc_status', 'withdraw_status',
        'login_count', 'last_login_ip', 'last_login_at', 'login_key',
        'ad_plan_id',
        'credit_score', 'is_credit_score_enabled', 'is_transfer_enabled',
        'sign_in_days', 'sign_last_at', 'lottery_count',
        'reward_multiplier', 'withdraw_level', 'withdraw_level_locked',
        'pay_password_changed_at', 'studio_certified', 'trial_no_recharge',
        'team_count', 'team_monthly_reset',
        'remark',
        'registered_at',
    ];

    protected $hidden = ['password', 'pay_password', 'login_key'];

    protected $casts = [
        'balance'           => 'decimal:4',
        'recharge_balance'  => 'decimal:4',
        'brokerage_balance' => 'decimal:4',
        'frozen_balance'    => 'decimal:4',
        'trial_balance'     => 'decimal:4',
        'points'            => 'decimal:4',
        'total_recharge'    => 'decimal:4',
        'total_withdraw'    => 'decimal:4',
        'total_earnings'    => 'decimal:4',
        'total_brokerage'   => 'decimal:4',
        'status'                   => 'integer',
        'is_activated'             => 'integer',
        'is_virtual'               => 'integer',
        'is_proxy'                 => 'integer',
        'kyc_status'               => 'integer',
        'withdraw_status'          => 'integer',
        'is_invite_code_enabled'       => 'integer',
        'is_credit_score_enabled'  => 'integer',
        'is_transfer_enabled'         => 'integer',
        'team_monthly_reset'       => 'integer',
        'reward_multiplier'        => 'decimal:2',
        'withdraw_level'           => 'integer',
        'withdraw_level_locked'    => 'integer',
        'pay_password_changed_at'  => 'integer',
        'studio_certified'         => 'integer',
        'trial_no_recharge'        => 'integer',
    ];

    public static function tableSchema(): array
    {
        $decimal = fn (int $precision, int $scale, $default = 0) => [
            'type' => 'decimal', 'precision' => $precision, 'scale' => $scale,
            'unsigned' => true, 'default' => $default,
        ];

        return [
            'table'   => 'me_user',
            'comment' => '用户主表',
            'columns' => [
                'id'        => ['type' => 'bigIncrements', 'comment' => '主键ID'],
                'username'  => ['type' => 'string', 'length' => 100, 'comment' => '登录账号'],
                'nickname'  => ['type' => 'string', 'length' => 100, 'default' => '', 'comment' => '昵称'],
                'real_name' => ['type' => 'string', 'length' => 50, 'default' => '', 'comment' => '真实姓名'],
                'mobile'    => ['type' => 'string', 'length' => 50, 'default' => '', 'comment' => '手机号'],
                'phone_code'=> ['type' => 'string', 'length' => 5, 'default' => '', 'comment' => '手机区号'],
                'email'     => ['type' => 'string', 'length' => 100, 'default' => '', 'comment' => '邮箱'],
                'password'  => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '登录密码(bcrypt)'],
                'pay_password'=>['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '支付密码(bcrypt)'],
                'avatar'    => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '头像地址'],
                'lang'      => ['type' => 'string', 'length' => 30, 'default' => '', 'comment' => '语言'],
                // 钱包
                'balance'           => $decimal(20, 4) + ['comment' => '基础钱包（实际可用余额）'],
                'recharge_balance'  => $decimal(20, 4) + ['comment' => '累计充值（仅记录，不可消费）'],
                'brokerage_balance' => $decimal(20, 4) + ['comment' => '佣金余额'],
                'frozen_balance'    => $decimal(20, 4) + ['comment' => '冻结金额(提现审核中)'],
                'trial_balance'     => $decimal(20, 4) + ['comment' => '体验金(不可提现)'],
                'points'            => $decimal(20, 4) + ['comment' => '积分'],
                'total_recharge'    => $decimal(20, 4) + ['comment' => '累计充值'],
                'total_withdraw'    => $decimal(20, 4) + ['comment' => '累计提现'],
                'total_earnings'    => $decimal(20, 4) + ['comment' => '累计收益'],
                'total_brokerage'   => $decimal(20, 4) + ['comment' => '累计佣金'],
                // 等级/层级
                'level_id'    => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '当前等级ID'],
                'leader1'     => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '一级上级'],
                'leader2'     => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '二级上级'],
                'leader3'     => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '三级上级'],
                'leader4'     => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '四级上级'],
                'leader5'     => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '五级上级'],
                'leader6'     => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '六级上级'],
                'leader7'     => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '七级上级'],
                'tree_id'     => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '团队树路径'],
                'invite_code' => ['type' => 'string', 'length' => 30, 'default' => '', 'comment' => '邀请码'],
                'is_invite_code_enabled' => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 1, 'comment' => '邀请码状态:0禁用 1正常'],
                // 状态
                'status'        => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 1, 'comment' => '状态:0禁用 1正常'],
                'is_activated'  => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '激活状态:0未激活 1已激活'],
                'is_virtual'    => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '虚拟用户:0否 1是'],
                'is_proxy'      => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '是否代理:0否 1是'],
                'kyc_status'    => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => 'KYC状态:0未认证 1已认证'],
                'withdraw_status'=>['type' => 'tinyInteger', 'unsigned' => true, 'default' => 1, 'comment' => '提现开关:0关闭 1开启'],
                // 安全
                'login_count'    => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '登录次数'],
                'last_login_ip'  => ['type' => 'string', 'length' => 45, 'default' => '', 'comment' => '最后登录IP'],
                'last_login_at'  => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '最后登录时间'],
                'login_key'      => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '登录Token'],
                // 业务配置
                'ad_plan_id' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '广告位计划ID'],
                'credit_score'              => ['type' => 'integer', 'default' => 0, 'comment' => '信用分'],
                'is_credit_score_enabled'   => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '信用评分开关'],
                'is_transfer_enabled'          => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '转账开关'],
                'sign_in_days'  => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '连续签到天数'],
                'sign_last_at'  => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '最后签到时间'],
                'lottery_count' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '抽奖次数'],
                'reward_multiplier' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'default' => 1, 'comment' => '邀请活动奖金倍数'],
                'withdraw_level' => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '提现等级'],
                'withdraw_level_locked' => ['type' => 'tinyInteger', 'default' => -1, 'comment' => '单控提现等级(-1未锁定)'],
                'pay_password_changed_at' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '支付密码最后修改时间'],
                'studio_certified' => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '工作室认证:0否 1是'],
                'trial_no_recharge' => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '领体验券后未充值'],
                'team_count'          => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '团队总人数'],
                'team_monthly_reset'  => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '团队月重置:0否 1是'],
                'remark'    => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '管理员备注'],
                // 时间戳
                'registered_at' => ['type' => 'unsignedInteger', 'default' => 0, 'comment' => '注册时间(unix)'],
                'deleted_at'    => ['type' => 'timestamp', 'nullable' => true, 'comment' => '软删除时间'],
            ],
            'timestamps'     => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
                'deleted_at' => '软删除时间',
            ],
            'unique'  => [['columns' => ['username']]],
            'indexes' => [
                ['columns' => ['mobile']],
                ['columns' => ['level_id']],
                ['columns' => ['leader1']],
                ['columns' => ['tree_id']],
                ['columns' => ['invite_code']],
                ['columns' => ['status']],
                ['columns' => ['is_virtual']],
                ['columns' => ['is_activated']],
                ['columns' => ['kyc_status']],
                ['columns' => ['is_proxy']],
                ['columns' => ['deleted_at']],
            ],
        ];
    }

    public function cards(): HasMany
    {
        return $this->hasMany(UserCardModel::class, 'user_id');
    }

    public function realNameAuths(): HasMany
    {
        return $this->hasMany(UserRealNameAuthModel::class, 'user_id');
    }

    public function waters(): HasMany
    {
        return $this->hasMany(UserWaterModel::class, 'user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(UserMessageModel::class, 'user_id');
    }

    public function ips(): HasMany
    {
        return $this->hasMany(UserIpModel::class, 'user_id');
    }

    /**
     * 代理/上级展示名：有备注优先备注，否则账号
     *
     * @param null|self|array{id?:int,username?:string,remark?:string} $user
     */
    public static function displayLabel(null|self|array $user, int $fallbackId = 0): string
    {
        if ($user instanceof self) {
            $remark = trim((string) ($user->remark ?? ''));
            $username = trim((string) ($user->username ?? ''));
            $id = (int) $user->id;
        } elseif (is_array($user)) {
            $remark = trim((string) ($user['remark'] ?? ''));
            $username = trim((string) ($user['username'] ?? ''));
            $id = (int) ($user['id'] ?? $fallbackId);
        } else {
            return $fallbackId > 0 ? ('ID:' . $fallbackId) : '-';
        }

        if ($remark !== '') {
            return $remark;
        }
        if ($username !== '') {
            return $username;
        }

        return $id > 0 ? ('ID:' . $id) : '-';
    }
}
