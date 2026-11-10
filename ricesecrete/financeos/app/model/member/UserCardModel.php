<?php

declare(strict_types=1);

namespace app\model\member;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCardModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'me_user_card';

    protected $fillable = [
        'user_id', 'type', 'bank_name', 'bank_code', 'card_number',
        'card_holder', 'bank_branch', 'bank_address', 'reserve_phone', 'chain_type',
        'is_default', 'status',
    ];

    public static function tableSchema(): array
    {
        return [
            'table'   => 'me_user_card',
            'comment' => '用户银行卡/支付宝绑定表',
            'columns' => [
                'id'       => ['type' => 'increments', 'comment' => '主键ID'],
                'user_id'  => ['type' => 'unsignedBigInteger', 'comment' => '用户ID'],
                'type'     => ['type' => 'enum', 'values' => ['bank', 'alipay', 'usdt'], 'default' => 'bank', 'comment' => '收款方式'],
                'bank_name'  => ['type' => 'string', 'length' => 100, 'default' => '', 'comment' => '银行名称'],
                'bank_code'  => ['type' => 'string', 'length' => 50, 'default' => '', 'comment' => '银行代码'],
                'card_number' => ['type' => 'string', 'length' => 50, 'default' => '', 'comment' => '卡号(加密存储)'],
                'card_holder' => ['type' => 'string', 'length' => 50, 'default' => '', 'comment' => '持卡人姓名'],
                'bank_branch' => ['type' => 'string', 'length' => 100, 'default' => '', 'comment' => '开户行'],
                'bank_address'=> ['type' => 'string', 'length' => 100, 'default' => '', 'comment' => '银行地址'],
                'reserve_phone' => ['type' => 'string', 'length' => 20, 'default' => '', 'comment' => '预留手机号'],
                'chain_type' => ['type' => 'string', 'length' => 20, 'default' => '', 'comment' => 'USDT链类型'],
                'is_default' => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '是否默认:0否 1是'],
                'status'     => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 1, 'comment' => '状态:0禁用 1正常'],
            ],
            'timestamps'     => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [
                ['columns' => ['user_id']],
                ['columns' => ['type']],
            ],
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }
}

