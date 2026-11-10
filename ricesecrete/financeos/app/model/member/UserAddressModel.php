<?php

declare(strict_types=1);

namespace app\model\member;

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAddressModel extends Model
{
    use DefinesTableSchema;

    protected $table = 'me_user_address';

    protected $fillable = [
        'user_id', 'consignee', 'mobile', 'region', 'address', 'zipcode',
        'is_default', 'status',
    ];

    public static function tableSchema(): array
    {
        return [
            'table'   => 'me_user_address',
            'comment' => '用户收货地址表',
            'columns' => [
                'id'        => ['type' => 'increments', 'comment' => '主键ID'],
                'user_id'   => ['type' => 'unsignedBigInteger', 'comment' => '用户ID'],
                'consignee' => ['type' => 'string', 'length' => 60, 'default' => '', 'comment' => '收货人'],
                'mobile'    => ['type' => 'string', 'length' => 60, 'default' => '', 'comment' => '手机号'],
                'region'    => ['type' => 'string', 'length' => 11, 'default' => '', 'comment' => '地区编码'],
                'address'   => ['type' => 'string', 'length' => 255, 'default' => '', 'comment' => '详细地址'],
                'zipcode'   => ['type' => 'string', 'length' => 20, 'default' => '', 'comment' => '邮编'],
                'is_default'=> ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 0, 'comment' => '是否默认:0否 1是'],
                'status'    => ['type' => 'tinyInteger', 'unsigned' => true, 'default' => 1, 'comment' => '状态:0禁用 1正常'],
            ],
            'timestamps'     => false,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }
}

